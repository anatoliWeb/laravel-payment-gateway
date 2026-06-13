<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Build authoritative billing report aggregates from the database.
 *
 * WHY:
 * The admin reports layer must never reconstruct totals from paginated UI
 * slices. Query-time aggregates keep revenue and usage numbers stable,
 * auditable, and safe for future dashboard consumers.
 */
class BillingReportService
{
    private const PAYMENT_SUCCESS_STATUS = 'succeeded';

    /**
     * @param array<string, int|string|null> $filters
     * @return array<string, mixed>
     */
    public function revenueSummary(array $filters): array
    {
        $payments = $this->paymentQuery($filters);
        $successfulPayments = $this->paymentQuery($filters, true);

        return $this->payload('revenue_summary', $filters, [
            'summary' => [
                'payment_count' => (clone $payments)->count(),
                'successful_payment_count' => (clone $successfulPayments)->count(),
                'revenue_amount' => (int) (clone $successfulPayments)->sum('amount'),
                'average_successful_payment_amount' => $this->averageAmount($successfulPayments),
            ],
            'currency_breakdown' => $this->currencyBreakdown($successfulPayments, 'payments.currency', 'amount', 'revenue_amount'),
            'status_breakdown' => $this->paymentStatusBreakdown($payments),
        ]);
    }

    /**
     * @param array<string, int|string|null> $filters
     * @return array<string, mixed>
     */
    public function paymentStatusSummary(array $filters): array
    {
        $payments = $this->paymentQuery($filters);

        return $this->payload('payment_status_summary', $filters, [
            'summary' => [
                'payment_count' => (clone $payments)->count(),
            ],
            'status_breakdown' => $this->paymentStatusBreakdown($payments),
        ]);
    }

    /**
     * @param array<string, int|string|null> $filters
     * @return array<string, mixed>
     */
    public function revenueByPlan(array $filters): array
    {
        $query = Payment::query()
            ->leftJoin('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->selectRaw('plans.id as plan_id, plans.slug as plan_slug, plans.name as plan_name, payments.currency as currency')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as revenue_amount')
            ->where('payments.status', self::PAYMENT_SUCCESS_STATUS)
            ->groupBy('plans.id', 'plans.slug', 'plans.name', 'payments.currency')
            ->orderByDesc('revenue_amount')
            ->orderByDesc('payment_count');

        $this->applyPaymentFilters($query, $filters, 'payments');

        if (($filters['plan_id'] ?? null) !== null) {
            $query->where('subscriptions.plan_id', (int) $filters['plan_id']);
        }

        return $this->payload('revenue_by_plan', $filters, [
            'rows' => $this->mapGroupedRows($query->get(), [
                'plan_id' => fn (object $row) => $row->plan_id !== null ? (int) $row->plan_id : null,
                'plan_slug' => fn (object $row) => $row->plan_slug,
                'plan_name' => fn (object $row) => $row->plan_name ?? 'Unassigned',
                'currency' => fn (object $row) => $row->currency,
                'payment_count' => fn (object $row) => (int) $row->payment_count,
                'revenue_amount' => fn (object $row) => (int) $row->revenue_amount,
            ]),
        ]);
    }

    /**
     * @param array<string, int|string|null> $filters
     * @return array<string, mixed>
     */
    public function revenueByCurrency(array $filters): array
    {
        $query = Payment::query()
            ->selectRaw('payments.currency as currency')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as revenue_amount')
            ->where('payments.status', self::PAYMENT_SUCCESS_STATUS)
            ->groupBy('payments.currency')
            ->orderByDesc('revenue_amount');

        $this->applyPaymentFilters($query, $filters, 'payments');

        return $this->payload('revenue_by_currency', $filters, [
            'rows' => $this->mapGroupedRows($query->get(), [
                'currency' => fn (object $row) => $row->currency,
                'payment_count' => fn (object $row) => (int) $row->payment_count,
                'revenue_amount' => fn (object $row) => (int) $row->revenue_amount,
            ]),
        ]);
    }

    /**
     * @param array<string, int|string|null> $filters
     * @return array<string, mixed>
     */
    public function revenueBySellerCompany(array $filters): array
    {
        $query = Payment::query()
            ->leftJoin('companies', 'payments.company_id', '=', 'companies.id')
            ->leftJoin('sellers', 'payments.seller_id', '=', 'sellers.id')
            ->selectRaw('payments.company_id as company_id, companies.name as company_name, payments.seller_id as seller_id, sellers.name as seller_name, payments.currency as currency')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as revenue_amount')
            ->where('payments.status', self::PAYMENT_SUCCESS_STATUS)
            ->groupBy('payments.company_id', 'companies.name', 'payments.seller_id', 'sellers.name', 'payments.currency')
            ->orderByDesc('revenue_amount');

        $this->applyPaymentFilters($query, $filters, 'payments');

        return $this->payload('revenue_by_seller_company', $filters, [
            'rows' => $this->mapGroupedRows($query->get(), [
                'company_id' => fn (object $row) => $row->company_id !== null ? (int) $row->company_id : null,
                'company_name' => fn (object $row) => $row->company_name ?? 'Unassigned',
                'seller_id' => fn (object $row) => $row->seller_id !== null ? (int) $row->seller_id : null,
                'seller_name' => fn (object $row) => $row->seller_name ?? 'Unassigned',
                'currency' => fn (object $row) => $row->currency,
                'payment_count' => fn (object $row) => (int) $row->payment_count,
                'revenue_amount' => fn (object $row) => (int) $row->revenue_amount,
            ]),
        ]);
    }

    /**
     * @param array<string, int|string|null> $filters
     * @return array<string, mixed>
     */
    public function subscriptionMetrics(array $filters): array
    {
        $query = Subscription::query();
        $this->applySubscriptionFilters($query, $filters, 'subscriptions');

        $statusRows = Subscription::query()
            ->selectRaw('subscriptions.status as status')
            ->selectRaw('COUNT(*) as subscription_count')
            ->groupBy('subscriptions.status')
            ->orderByDesc('subscription_count');
        $this->applySubscriptionFilters($statusRows, $filters, 'subscriptions');

        $planRows = Subscription::query()
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->selectRaw('plans.id as plan_id, plans.slug as plan_slug, plans.name as plan_name')
            ->selectRaw('COUNT(*) as subscription_count')
            ->groupBy('plans.id', 'plans.slug', 'plans.name')
            ->orderByDesc('subscription_count');
        $this->applySubscriptionFilters($planRows, $filters, 'subscriptions');

        return $this->payload('subscription_metrics', $filters, [
            'summary' => [
                'subscription_count' => (clone $query)->count(),
                'active_subscription_count' => (clone $query)->where('subscriptions.status', 'active')->count(),
                'trialing_subscription_count' => (clone $query)->where('subscriptions.status', 'trialing')->count(),
                'past_due_subscription_count' => (clone $query)->where('subscriptions.status', 'past_due')->count(),
                'cancelled_subscription_count' => (clone $query)->where('subscriptions.status', 'cancelled')->count(),
                'new_subscription_count' => (clone $query)->count(),
            ],
            'status_breakdown' => $this->mapGroupedRows($statusRows->get(), [
                'status' => fn (object $row) => $row->status,
                'subscription_count' => fn (object $row) => (int) $row->subscription_count,
            ]),
            'plan_breakdown' => $this->mapGroupedRows($planRows->get(), [
                'plan_id' => fn (object $row) => $row->plan_id !== null ? (int) $row->plan_id : null,
                'plan_slug' => fn (object $row) => $row->plan_slug,
                'plan_name' => fn (object $row) => $row->plan_name ?? 'Unassigned',
                'subscription_count' => fn (object $row) => (int) $row->subscription_count,
            ]),
        ]);
    }

    /**
     * @param array<string, int|string|null> $filters
     * @return array<string, mixed>
     */
    public function invoiceMetrics(array $filters): array
    {
        $query = Invoice::query();
        $this->applyInvoiceFilters($query, $filters, 'invoices');

        $statusRows = Invoice::query()
            ->selectRaw('invoices.status as status')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(invoices.paid_amount), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(invoices.due_amount), 0) as due_amount')
            ->groupBy('invoices.status')
            ->orderByDesc('invoice_count');
        $this->applyInvoiceFilters($statusRows, $filters, 'invoices');

        $currencyRows = Invoice::query()
            ->selectRaw('invoices.currency as currency')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(invoices.paid_amount), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(invoices.due_amount), 0) as due_amount')
            ->groupBy('invoices.currency')
            ->orderByDesc('total_amount');
        $this->applyInvoiceFilters($currencyRows, $filters, 'invoices');

        return $this->payload('invoice_metrics', $filters, [
            'summary' => [
                'invoice_count' => (clone $query)->count(),
                'issued_invoice_count' => (clone $query)->where('invoices.status', Invoice::STATUS_ISSUED)->count(),
                'paid_invoice_count' => (clone $query)->where('invoices.status', Invoice::STATUS_PAID)->count(),
                'void_invoice_count' => (clone $query)->where('invoices.status', Invoice::STATUS_VOID)->count(),
                'overdue_invoice_count' => (clone $query)->where('invoices.status', Invoice::STATUS_OVERDUE)->count(),
            ],
            'status_breakdown' => $this->mapGroupedRows($statusRows->get(), [
                'status' => fn (object $row) => $row->status,
                'invoice_count' => fn (object $row) => (int) $row->invoice_count,
                'total_amount' => fn (object $row) => (int) $row->total_amount,
                'paid_amount' => fn (object $row) => (int) $row->paid_amount,
                'due_amount' => fn (object $row) => (int) $row->due_amount,
            ]),
            'currency_breakdown' => $this->mapGroupedRows($currencyRows->get(), [
                'currency' => fn (object $row) => $row->currency,
                'invoice_count' => fn (object $row) => (int) $row->invoice_count,
                'total_amount' => fn (object $row) => (int) $row->total_amount,
                'paid_amount' => fn (object $row) => (int) $row->paid_amount,
                'due_amount' => fn (object $row) => (int) $row->due_amount,
            ]),
        ]);
    }

    /**
     * @param array<string, int|string|null> $filters
     * @return array<string, mixed>
     */
    public function walletMetrics(array $filters): array
    {
        $wallets = Wallet::query();
        $this->applyWalletFilters($wallets, $filters, 'wallets');

        $transactions = WalletTransaction::query();
        $this->applyWalletTransactionFilters($transactions, $filters, 'wallet_transactions');

        $walletStatusRows = Wallet::query()
            ->selectRaw('wallets.status as status')
            ->selectRaw('COUNT(*) as wallet_count')
            ->groupBy('wallets.status')
            ->orderByDesc('wallet_count');
        $this->applyWalletFilters($walletStatusRows, $filters, 'wallets');

        $currencyRows = WalletBalance::query()
            ->leftJoin('wallets', 'wallet_balances.wallet_id', '=', 'wallets.id')
            ->leftJoin('currencies', 'wallet_balances.currency_id', '=', 'currencies.id')
            ->selectRaw('currencies.code as currency')
            ->selectRaw('COUNT(*) as balance_count')
            ->selectRaw('COALESCE(SUM(wallet_balances.available_amount), 0) as available_amount')
            ->selectRaw('COALESCE(SUM(wallet_balances.held_amount), 0) as held_amount')
            ->groupBy('currencies.code')
            ->orderByDesc('available_amount');
        $this->applyWalletFilters($currencyRows, $filters, 'wallets');
        if (($filters['currency'] ?? null) !== null) {
            $currencyRows->where('currencies.code', (string) $filters['currency']);
        }

        $transactionRows = WalletTransaction::query()
            ->leftJoin('currencies', 'wallet_transactions.currency_id', '=', 'currencies.id')
            ->selectRaw('wallet_transactions.type as type')
            ->selectRaw('wallet_transactions.direction as direction')
            ->selectRaw('wallet_transactions.status as status')
            ->selectRaw('currencies.code as currency')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN wallet_transactions.direction = "credit" THEN wallet_transactions.amount ELSE 0 END), 0) as credited_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN wallet_transactions.direction = "debit" THEN wallet_transactions.amount ELSE 0 END), 0) as debited_amount')
            ->groupBy('wallet_transactions.type', 'wallet_transactions.direction', 'wallet_transactions.status', 'currencies.code')
            ->orderByDesc('transaction_count');
        $this->applyWalletTransactionFilters($transactionRows, $filters, 'wallet_transactions');
        if (($filters['currency'] ?? null) !== null) {
            $transactionRows->where('currencies.code', (string) $filters['currency']);
        }

        return $this->payload('wallet_metrics', $filters, [
            'summary' => [
                'wallet_count' => (clone $wallets)->count(),
                'active_wallet_count' => (clone $wallets)->where('wallets.status', 'active')->count(),
                'suspended_wallet_count' => (clone $wallets)->where('wallets.status', 'suspended')->count(),
                'closed_wallet_count' => (clone $wallets)->where('wallets.status', 'closed')->count(),
                'transaction_count' => (clone $transactions)->count(),
            ],
            'wallet_status_breakdown' => $this->mapGroupedRows($walletStatusRows->get(), [
                'status' => fn (object $row) => $row->status,
                'wallet_count' => fn (object $row) => (int) $row->wallet_count,
            ]),
            'currency_breakdown' => $this->mapGroupedRows($currencyRows->get(), [
                'currency' => fn (object $row) => $row->currency,
                'balance_count' => fn (object $row) => (int) $row->balance_count,
                'available_amount' => fn (object $row) => (int) $row->available_amount,
                'held_amount' => fn (object $row) => (int) $row->held_amount,
            ]),
            'transaction_breakdown' => $this->mapGroupedRows($transactionRows->get(), [
                'type' => fn (object $row) => $row->type,
                'direction' => fn (object $row) => $row->direction,
                'status' => fn (object $row) => $row->status,
                'currency' => fn (object $row) => $row->currency,
                'transaction_count' => fn (object $row) => (int) $row->transaction_count,
                'credited_amount' => fn (object $row) => (int) $row->credited_amount,
                'debited_amount' => fn (object $row) => (int) $row->debited_amount,
            ]),
        ]);
    }

    /**
     * @param array<string, int|string|null> $filters
     * @return Builder<Payment>
     */
    private function paymentQuery(array $filters, bool $successfulOnly = false): Builder
    {
        $query = Payment::query();
        $this->applyPaymentFilters($query, $filters, 'payments');

        if ($successfulOnly) {
            $query->where('payments.status', self::PAYMENT_SUCCESS_STATUS);
        }

        return $query;
    }

    /**
     * @param array<string, int|string|null> $filters
     * @param Builder<Invoice>|Builder<Subscription>|Builder<Wallet>|Builder<WalletTransaction>|Builder<Payment> $query
     * @param string $alias
     */
    private function applyDateFilter(Builder $query, array $filters, string $alias): void
    {
        if (($filters['date_from'] ?? null) === null && ($filters['date_to'] ?? null) === null) {
            return;
        }

        $from = ($filters['date_from'] ?? null) !== null
            ? CarbonImmutable::parse((string) $filters['date_from'])->startOfDay()
            : CarbonImmutable::parse('1970-01-01 00:00:00');
        $to = ($filters['date_to'] ?? null) !== null
            ? CarbonImmutable::parse((string) $filters['date_to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $query->whereBetween("{$alias}.created_at", [$from, $to]);
    }

    /**
     * @param array<string, int|string|null> $filters
     * @param Builder<Payment> $query
     * @param string $alias
     */
    private function applyPaymentFilters(Builder $query, array $filters, string $alias): void
    {
        $this->applyDateFilter($query, $filters, $alias);

        if (($filters['currency'] ?? null) !== null) {
            $query->where("{$alias}.currency", (string) $filters['currency']);
        }

        if (($filters['payment_status'] ?? null) !== null) {
            $query->where("{$alias}.status", (string) $filters['payment_status']);
        }

        if (($filters['company_id'] ?? null) !== null) {
            $query->where("{$alias}.company_id", (int) $filters['company_id']);
        }

        if (($filters['seller_id'] ?? null) !== null) {
            $query->where("{$alias}.seller_id", (int) $filters['seller_id']);
        }

        if (($filters['user_id'] ?? null) !== null) {
            $query->where("{$alias}.user_id", (int) $filters['user_id']);
        }

        if (($filters['plan_id'] ?? null) !== null) {
            $query->whereHas('subscription', fn (Builder $subscriptionQuery) => $subscriptionQuery->where('plan_id', (int) $filters['plan_id']));
        }
    }

    /**
     * @param array<string, int|string|null> $filters
     * @param Builder<Invoice> $query
     * @param string $alias
     */
    private function applyInvoiceFilters(Builder $query, array $filters, string $alias): void
    {
        $this->applyDateFilter($query, $filters, $alias);

        if (($filters['currency'] ?? null) !== null) {
            $query->where("{$alias}.currency", (string) $filters['currency']);
        }

        if (($filters['invoice_status'] ?? null) !== null) {
            $query->where("{$alias}.status", (string) $filters['invoice_status']);
        }

        if (($filters['company_id'] ?? null) !== null) {
            $query->where("{$alias}.company_id", (int) $filters['company_id']);
        }

        if (($filters['seller_id'] ?? null) !== null) {
            $query->where("{$alias}.seller_id", (int) $filters['seller_id']);
        }

        if (($filters['user_id'] ?? null) !== null) {
            $query->where("{$alias}.user_id", (int) $filters['user_id']);
        }

        if (($filters['plan_id'] ?? null) !== null) {
            $query->whereHas('subscription', fn (Builder $subscriptionQuery) => $subscriptionQuery->where('plan_id', (int) $filters['plan_id']));
        }
    }

    /**
     * @param array<string, int|string|null> $filters
     * @param Builder<Subscription> $query
     * @param string $alias
     */
    private function applySubscriptionFilters(Builder $query, array $filters, string $alias): void
    {
        $this->applyDateFilter($query, $filters, $alias);

        if (($filters['subscription_status'] ?? null) !== null) {
            $query->where("{$alias}.status", (string) $filters['subscription_status']);
        }

        if (($filters['plan_id'] ?? null) !== null) {
            $query->where("{$alias}.plan_id", (int) $filters['plan_id']);
        }

        if (($filters['user_id'] ?? null) !== null) {
            $query->where("{$alias}.user_id", (int) $filters['user_id']);
        }
    }

    /**
     * @param array<string, int|string|null> $filters
     * @param Builder<Wallet> $query
     * @param string $alias
     */
    private function applyWalletFilters(Builder $query, array $filters, string $alias): void
    {
        $this->applyDateFilter($query, $filters, $alias);

        if (($filters['wallet_status'] ?? null) !== null) {
            $query->where("{$alias}.status", (string) $filters['wallet_status']);
        }

        if (($filters['user_id'] ?? null) !== null) {
            $query->where("{$alias}.user_id", (int) $filters['user_id']);
        }
    }

    /**
     * @param array<string, int|string|null> $filters
     * @param Builder<WalletTransaction> $query
     * @param string $alias
     */
    private function applyWalletTransactionFilters(Builder $query, array $filters, string $alias): void
    {
        $this->applyDateFilter($query, $filters, $alias);

        if (($filters['wallet_status'] ?? null) !== null) {
            $query->whereHas('wallet', fn (Builder $walletQuery) => $walletQuery->where('status', (string) $filters['wallet_status']));
        }

        if (($filters['currency'] ?? null) !== null) {
            $query->whereHas('currency', fn (Builder $currencyQuery) => $currencyQuery->where('code', (string) $filters['currency']));
        }

        if (($filters['user_id'] ?? null) !== null) {
            $query->whereHas('wallet', fn (Builder $walletQuery) => $walletQuery->where('user_id', (int) $filters['user_id']));
        }
    }

    /**
     * @param Builder<Payment> $payments
     * @return array<int, array<string, mixed>>
     */
    private function paymentStatusBreakdown(Builder $payments): array
    {
        $rows = (clone $payments)
            ->selectRaw('payments.status as status')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as amount_total')
            ->groupBy('payments.status')
            ->orderByDesc('payment_count')
            ->get();

        return $this->mapGroupedRows($rows, [
            'status' => fn (object $row) => $row->status,
            'payment_count' => fn (object $row) => (int) $row->payment_count,
            'amount_total' => fn (object $row) => (int) $row->amount_total,
        ]);
    }

    /**
     * @param Builder<Payment> $query
     * @param string $groupColumn
     * @param string $amountColumn
     * @param string $amountKey
     * @return array<int, array<string, mixed>>
     */
    private function currencyBreakdown(Builder $query, string $groupColumn, string $amountColumn, string $amountKey): array
    {
        $rows = (clone $query)
            ->selectRaw("{$groupColumn} as currency")
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw("COALESCE(SUM({$amountColumn}), 0) as {$amountKey}")
            ->groupBy($groupColumn)
            ->orderByDesc($amountKey)
            ->get();

        return $this->mapGroupedRows($rows, [
            'currency' => fn (object $row) => $row->currency,
            'payment_count' => fn (object $row) => (int) $row->payment_count,
            $amountKey => fn (object $row) => (int) $row->{$amountKey},
        ]);
    }

    /**
     * @param Builder<Payment> $query
     */
    private function averageAmount(Builder $query): ?int
    {
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        return (int) round(((clone $query)->sum('amount')) / $count);
    }

    /**
     * @template T of object
     * @param iterable<T> $rows
     * @param array<string, callable(T): mixed> $mapping
     * @return array<int, array<string, mixed>>
     */
    private function mapGroupedRows(iterable $rows, array $mapping): array
    {
        $mapped = [];

        foreach ($rows as $row) {
            $item = [];

            foreach ($mapping as $key => $callback) {
                $item[$key] = $callback($row);
            }

            $mapped[] = $item;
        }

        return $mapped;
    }

    /**
     * @param array<string, int|string|null> $filters
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function payload(string $scope, array $filters, array $payload): array
    {
        return array_merge([
            'scope' => $scope,
            'generated_at' => now()->toISOString(),
            'filters' => $this->normalizedFilters($filters),
        ], $payload);
    }

    /**
     * @param array<string, int|string|null> $filters
     * @return array<string, int|string|null>
     */
    private function normalizedFilters(array $filters): array
    {
        return array_filter([
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'currency' => $filters['currency'] ?? null,
            'payment_status' => $filters['payment_status'] ?? null,
            'invoice_status' => $filters['invoice_status'] ?? null,
            'subscription_status' => $filters['subscription_status'] ?? null,
            'wallet_status' => $filters['wallet_status'] ?? null,
            'plan_id' => $filters['plan_id'] ?? null,
            'company_id' => $filters['company_id'] ?? null,
            'seller_id' => $filters['seller_id'] ?? null,
            'user_id' => $filters['user_id'] ?? null,
        ], static fn ($value) => $value !== null);
    }
}
