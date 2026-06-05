<?php

namespace App\Services\Billing;

use App\DTO\Payments\CreatePaymentData;
use App\Events\Billing\InvoiceFailed;
use App\Events\Billing\InvoiceIssued;
use App\Events\Billing\InvoicePaid;
use App\Events\Billing\InvoicePaymentPending;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\Payments\IdempotencyService;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Coordinates invoice lifecycle transitions, totals, ownership, and payment linking.
 */
class InvoiceService
{
    public function __construct(
        private readonly OwnershipScopeService $ownershipScopeService,
        private readonly ActivityService $activityService,
        private readonly IdempotencyService $idempotencyService,
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * Create a draft invoice with calculated line item totals.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $context
     */
    public function createDraftInvoice(User $payer, array $items, array $context = []): Invoice
    {
        $idempotencyKey = trim((string) ($context['idempotency_key'] ?? ''));
        $payload = $this->idempotencyPayload($payer, $items, $context);

        if ($idempotencyKey !== '') {
            $replay = $this->idempotencyService->replay($idempotencyKey, 'invoice.create', $payload, $payer);
            if ($replay !== null) {
                return $this->replayInvoice($replay);
            }
        }

        $record = $idempotencyKey !== ''
            ? $this->idempotencyService->start($idempotencyKey, 'invoice.create', $payload, $payer)
            : null;

        try {
            $invoice = DB::transaction(function () use ($payer, $items, $context): Invoice {
                if ($items === []) {
                    throw new RuntimeException('invoice_items_required');
                }

                $ownership = $this->ownershipScopeService->resolveForPayment($payer, [
                    'company_id' => $context['company_id'] ?? null,
                    'seller_id' => $context['seller_id'] ?? null,
                ]);

                $invoice = Invoice::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'number' => null,
                    'user_id' => $payer->id,
                    'payer_user_id' => $ownership['payer_user_id'],
                    'company_id' => $ownership['company_id'],
                    'seller_id' => $ownership['seller_id'],
                    'subscription_id' => $context['subscription_id'] ?? null,
                    'payment_id' => null,
                    'status' => Invoice::STATUS_DRAFT,
                    'currency' => strtoupper((string) ($context['currency'] ?? 'USD')),
                    'description' => $context['description'] ?? null,
                    'due_at' => $context['due_at'] ?? null,
                    'metadata' => $this->sanitizeMetadata((array) ($context['metadata'] ?? [])),
                    'ownership_metadata' => $ownership['ownership_metadata'],
                ]);

                foreach ($items as $item) {
                    $this->createItem($invoice, $item);
                }

                $this->recalculateTotals($invoice);
                $this->recordActivity($invoice->refresh(), 'billing.invoice_created', 'Billing invoice created');

                return $invoice->refresh()->load('items');
            });

            if ($record !== null) {
                $this->idempotencyService->complete($record, ['invoice_id' => $invoice->id], $invoice->id, Invoice::class);
            }

            return $invoice;
        } catch (Throwable $exception) {
            if ($record?->fresh()?->status === 'processing') {
                $this->idempotencyService->fail($record, $exception->getMessage() ?: 'invoice_creation_failed');
            }

            throw $exception;
        }
    }

    public function issueInvoice(Invoice $invoice, User $actor): Invoice
    {
        $issued = DB::transaction(function () use ($invoice, $actor): Invoice {
            $invoice = $invoice->lockForUpdate()->firstWhere('id', $invoice->id);
            if (! $invoice) {
                throw new RuntimeException('invoice_not_found');
            }

            $this->assertTransition($invoice, Invoice::STATUS_ISSUED);
            $this->recalculateTotals($invoice);

            if ($invoice->items()->count() === 0 || $invoice->total_amount <= 0) {
                throw new RuntimeException('invoice_cannot_issue_empty');
            }

            $invoice->update([
                'status' => Invoice::STATUS_ISSUED,
                'number' => $invoice->number ?? $this->nextInvoiceNumber(),
                'issued_at' => now(),
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'issued_by_user_id' => $actor->id,
                ]),
            ]);

            $this->recordActivity($invoice->refresh(), 'billing.invoice_issued', 'Billing invoice issued');

            return $invoice->refresh()->load('items');
        });

        event(new InvoiceIssued($issued));

        return $issued;
    }

    public function markPaymentPending(Invoice $invoice, Payment $payment): Invoice
    {
        [$pending, $changed] = DB::transaction(function () use ($invoice, $payment): array {
            $invoice = $invoice->lockForUpdate()->firstWhere('id', $invoice->id);
            if (! $invoice) {
                throw new RuntimeException('invoice_not_found');
            }

            if ($invoice->status === Invoice::STATUS_PAYMENT_PENDING
                && (int) $invoice->payment_id === (int) $payment->id) {
                $payment->update(['invoice_id' => $invoice->id]);

                return [$invoice->refresh()->load('items', 'payment'), false];
            }

            $this->assertTransition($invoice, Invoice::STATUS_PAYMENT_PENDING);

            // WHY: Payment attempts remain separate audit records; invoice keeps only latest linked attempt.
            $invoice->update([
                'status' => Invoice::STATUS_PAYMENT_PENDING,
                'payment_id' => $payment->id,
            ]);

            $payment->update(['invoice_id' => $invoice->id]);

            $this->recordActivity($invoice->refresh(), 'billing.invoice_payment_pending', 'Billing invoice payment pending', $payment);

            return [$invoice->refresh()->load('items', 'payment'), true];
        });

        if ($changed) {
            event(new InvoicePaymentPending($pending));
        }

        return $pending;
    }

    public function markPaid(Invoice $invoice, Payment $payment): Invoice
    {
        $paid = DB::transaction(function () use ($invoice, $payment): Invoice {
            $invoice = $invoice->lockForUpdate()->firstWhere('id', $invoice->id);
            if (! $invoice) {
                throw new RuntimeException('invoice_not_found');
            }

            $this->assertTransition($invoice, Invoice::STATUS_PAID);

            if ((int) $payment->invoice_id !== (int) $invoice->id && (int) $invoice->payment_id !== (int) $payment->id) {
                throw new RuntimeException('payment_not_linked_to_invoice');
            }

            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'payment_id' => $payment->id,
                'paid_amount' => $invoice->total_amount,
                'due_amount' => 0,
                'paid_at' => now(),
            ]);

            $this->recordActivity($invoice->refresh(), 'billing.invoice_paid', 'Billing invoice paid', $payment);

            return $invoice->refresh()->load('items', 'payment');
        });

        event(new InvoicePaid($paid));

        return $paid;
    }

    public function markFailed(Invoice $invoice, ?Payment $payment = null, ?string $reason = null): Invoice
    {
        $failed = DB::transaction(function () use ($invoice, $payment, $reason): Invoice {
            $invoice = $invoice->lockForUpdate()->firstWhere('id', $invoice->id);
            if (! $invoice) {
                throw new RuntimeException('invoice_not_found');
            }

            $this->assertTransition($invoice, Invoice::STATUS_FAILED);

            $invoice->update([
                'status' => Invoice::STATUS_FAILED,
                'payment_id' => $payment?->id ?? $invoice->payment_id,
                'metadata' => array_merge($invoice->metadata ?? [], array_filter([
                    'failure_reason' => $reason,
                ])),
            ]);

            $this->recordActivity($invoice->refresh(), 'billing.invoice_failed', 'Billing invoice failed', $payment);

            return $invoice->refresh()->load('items', 'payment');
        });

        event(new InvoiceFailed($failed));

        return $failed;
    }

    public function voidInvoice(Invoice $invoice, User $actor, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason): Invoice {
            $invoice = $invoice->lockForUpdate()->firstWhere('id', $invoice->id);
            if (! $invoice) {
                throw new RuntimeException('invoice_not_found');
            }

            $this->assertTransition($invoice, Invoice::STATUS_VOID);

            $invoice->update([
                'status' => Invoice::STATUS_VOID,
                'voided_at' => now(),
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'voided_by_user_id' => $actor->id,
                    'void_reason' => $reason,
                ]),
            ]);

            $this->recordActivity($invoice->refresh(), 'billing.invoice_voided', 'Billing invoice voided');

            return $invoice->refresh()->load('items');
        });
    }

    public function recalculateTotals(Invoice $invoice): Invoice
    {
        $items = $invoice->items()->get();
        $subtotal = (int) $items->sum('subtotal_amount');
        $discount = (int) $items->sum('discount_amount');
        $tax = (int) $items->sum('tax_amount');
        $total = max(0, $subtotal - $discount + $tax);
        $paid = min((int) $invoice->paid_amount, $total);

        $invoice->update([
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'due_amount' => max(0, $total - $paid),
        ]);

        return $invoice->refresh();
    }

    /**
     * Create a payment attempt for the invoice due amount without settling the invoice.
     *
     * @param array<string, mixed> $paymentData
     */
    public function createPaymentForInvoice(Invoice $invoice, User $payer, array $paymentData): Payment
    {
        $invoice = $invoice->fresh(['payer', 'subscription']);
        if (! $invoice) {
            throw new RuntimeException('invoice_not_found');
        }

        if ((int) $invoice->payer_user_id !== (int) $payer->id) {
            throw new RuntimeException('invoice_payer_mismatch');
        }

        if (! in_array($invoice->status, [
            Invoice::STATUS_ISSUED,
            Invoice::STATUS_PAYMENT_PENDING,
            Invoice::STATUS_FAILED,
            Invoice::STATUS_OVERDUE,
        ], true)) {
            throw new RuntimeException('invoice_payment_not_allowed');
        }

        if ($invoice->due_amount <= 0) {
            throw new RuntimeException('invoice_has_no_due_amount');
        }

        $currency = strtoupper((string) ($paymentData['currency'] ?? $invoice->currency));
        if ($currency !== $invoice->currency) {
            throw new RuntimeException('invoice_payment_currency_mismatch');
        }

        $payment = $this->paymentService->createPayment(new CreatePaymentData(
            user: $payer,
            subscriptionId: $invoice->subscription_id,
            planSlug: null,
            amount: $invoice->due_amount,
            currency: $invoice->currency,
            paymentSource: $paymentData['payment_source'] ?? null,
            paymentStrategy: $paymentData['payment_strategy'] ?? null,
            paymentMethodId: $paymentData['payment_method_id'] ?? null,
            callbackUrl: $paymentData['callback_url'] ?? null,
            description: $paymentData['description'] ?? "Payment for invoice {$invoice->number}",
            metadata: array_merge($this->sanitizeMetadata((array) ($paymentData['metadata'] ?? [])), [
                'invoice_id' => $invoice->id,
                'invoice_uuid' => $invoice->uuid,
                'invoice_number' => $invoice->number,
            ]),
            idempotencyKey: (string) ($paymentData['idempotency_key'] ?? ''),
            companyId: $invoice->company_id,
            sellerId: $invoice->seller_id,
        ));

        $this->markPaymentPending($invoice, $payment);

        return $payment->refresh();
    }

    private function createItem(Invoice $invoice, array $item): void
    {
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unit = (int) ($item['unit_amount'] ?? 0);
        if ($unit < 0) {
            throw new RuntimeException('invoice_item_unit_amount_invalid');
        }

        $subtotal = $quantity * $unit;
        $discount = max(0, (int) ($item['discount_amount'] ?? 0));
        $tax = max(0, (int) ($item['tax_amount'] ?? 0));
        $total = max(0, $subtotal - $discount + $tax);

        $invoice->items()->create([
            'item_type' => $item['item_type'] ?? null,
            'description' => (string) ($item['description'] ?? 'Invoice item'),
            'quantity' => $quantity,
            'unit_amount' => $unit,
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'metadata' => $this->sanitizeMetadata((array) ($item['metadata'] ?? [])),
        ]);
    }

    private function assertTransition(Invoice $invoice, string $targetStatus): void
    {
        if (in_array($invoice->status, Invoice::FINAL_STATUSES, true)) {
            throw new RuntimeException('invoice_status_is_final');
        }

        $allowed = [
            Invoice::STATUS_DRAFT => [Invoice::STATUS_ISSUED],
            Invoice::STATUS_ISSUED => [Invoice::STATUS_PAYMENT_PENDING, Invoice::STATUS_VOID],
            Invoice::STATUS_PAYMENT_PENDING => [Invoice::STATUS_PAID, Invoice::STATUS_FAILED, Invoice::STATUS_OVERDUE],
            Invoice::STATUS_FAILED => [Invoice::STATUS_PAYMENT_PENDING],
            Invoice::STATUS_OVERDUE => [Invoice::STATUS_PAYMENT_PENDING],
        ];

        if (! in_array($targetStatus, $allowed[$invoice->status] ?? [], true)) {
            throw new RuntimeException('invalid_invoice_status_transition');
        }
    }

    private function nextInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }

    private function replayInvoice(array $payload): Invoice
    {
        if (isset($payload['error_code'])) {
            throw new RuntimeException((string) $payload['error_code']);
        }

        $invoice = Invoice::query()->find($payload['invoice_id'] ?? null);
        if (! $invoice) {
            throw new RuntimeException('idempotency_replay_resource_missing');
        }

        return $invoice->load('items');
    }

    private function idempotencyPayload(User $payer, array $items, array $context): array
    {
        unset($context['idempotency_key']);

        return [
            'payer_user_id' => $payer->id,
            'items' => $this->sanitizeMetadata($items),
            'context' => $this->sanitizeMetadata($context),
        ];
    }

    private function recordActivity(Invoice $invoice, string $action, string $description, ?Payment $payment = null): void
    {
        try {
            $this->activityService->log($invoice->payer_user_id, $action, $description, [
                'source' => 'invoice_service',
                'module' => 'billing',
                'invoice_id' => $invoice->id,
                'invoice_uuid' => $invoice->uuid,
                'invoice_number' => $invoice->number,
                'status' => $invoice->status,
                'total_amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
                'payer_user_id' => $invoice->payer_user_id,
                'company_id' => $invoice->company_id,
                'seller_id' => $invoice->seller_id,
                'payment_id' => $payment?->id,
            ]);
        } catch (Throwable) {
            // Activity logging must not break invoice lifecycle transitions.
        }
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $forbidden = ['card_number', 'pan', 'cvv', 'cvc', 'security_code', 'token', 'secret', 'password', 'private_key', 'idempotency_key'];

        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), $forbidden, true)) {
                unset($metadata[$key]);
                continue;
            }

            if (is_array($value)) {
                $metadata[$key] = $this->sanitizeMetadata($value);
            }
        }

        return $metadata;
    }
}
