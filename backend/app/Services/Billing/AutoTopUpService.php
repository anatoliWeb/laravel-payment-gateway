<?php

namespace App\Services\Billing;

use App\DTO\Payments\CreatePaymentData;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\ActivityService;
use App\Services\Billing\BillingRestrictionService;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Str;
use Throwable;

class AutoTopUpService
{
    public function __construct(
        private readonly PaymentPreferenceService $paymentPreferenceService,
        private readonly WalletService $walletService,
        private readonly WalletTransactionService $walletTransactionService,
        private readonly BillingRestrictionService $billingRestrictionService,
        private readonly PaymentService $paymentService,
        private readonly ActivityService $activityService,
    ) {
    }

    public function shouldAutoTopUp(User $user, string $currencyCode): array
    {
        $currency = $this->activeCurrency($currencyCode);
        if (! $currency) {
            return $this->blocked('invalid_currency');
        }

        $preference = $this->paymentPreferenceService->getOrCreatePreferences($user);

        if (! $preference->auto_top_up_enabled) {
            return $this->blocked('auto_top_up_disabled');
        }

        if (! $preference->auto_top_up_consent_at) {
            return $this->blocked('auto_top_up_consent_missing');
        }

        if ((int) $preference->auto_top_up_currency_id !== (int) $currency->id) {
            return $this->blocked('currency_mismatch');
        }

        if ((int) $preference->auto_top_up_amount <= 0) {
            return $this->blocked('auto_top_up_amount_invalid');
        }

        $paymentMethod = $this->defaultPaymentMethod($user);
        if (! $paymentMethod) {
            return $this->blocked('payment_method_not_found');
        }

        if ($paymentMethod->status !== 'active') {
            return $this->blocked('payment_method_inactive');
        }

        if ($this->billingRestrictionService->isPaymentBlocked($user)) {
            return $this->blocked('payment_risk_blocked');
        }

        $available = $this->walletService->getBalance($user, $currency->code)?->available_amount ?? 0;
        if ($available > (int) $preference->auto_top_up_threshold_amount) {
            return $this->blocked('balance_above_threshold', [
                'available_amount' => $available,
                'threshold_amount' => $preference->auto_top_up_threshold_amount,
            ]);
        }

        if ($this->limitReached($user, 'day', $preference->max_auto_top_up_per_day)) {
            return $this->blocked('auto_top_up_daily_limit_exceeded');
        }

        if ($this->limitReached($user, 'month', $preference->max_auto_top_up_per_month)) {
            return $this->blocked('auto_top_up_monthly_limit_exceeded');
        }

        return $this->allowed([
            'available_amount' => $available,
            'threshold_amount' => $preference->auto_top_up_threshold_amount,
            'top_up_amount' => $preference->auto_top_up_amount,
            'payment_method_id' => $paymentMethod->id,
        ]);
    }

    public function attemptAutoTopUp(User $user, string $currencyCode, ?string $idempotencyKey = null): array
    {
        $this->recordActivity($user, 'billing.auto_top_up_attempted', [
            'currency' => strtoupper($currencyCode),
        ]);

        $decision = $this->shouldAutoTopUp($user, $currencyCode);
        if (! $decision['allowed']) {
            $this->recordActivity($user, 'billing.auto_top_up_failed', [
                'reason' => $decision['reason'],
                'currency' => strtoupper($currencyCode),
            ]);

            return array_merge($decision, ['attempted' => true]);
        }

        $preference = $this->paymentPreferenceService->getOrCreatePreferences($user);
        $topUpAmount = (int) $preference->auto_top_up_amount;
        $key = $idempotencyKey ?? 'auto_top_up_'.Str::uuid();

        try {
            $payment = $this->paymentService->createPayment(new CreatePaymentData(
                user: $user,
                subscriptionId: null,
                planSlug: null,
                amount: $topUpAmount,
                currency: strtoupper($currencyCode),
                paymentSource: 'payment_method',
                paymentStrategy: 'payment_method_only',
                paymentMethodId: null,
                callbackUrl: null,
                description: 'Automatic wallet top-up',
                metadata: [
                    'source' => 'auto_top_up',
                    'auto_top_up' => true,
                ],
                idempotencyKey: $key,
            ));

            $walletTransaction = $this->walletTransactionService->credit(
                user: $user,
                currencyCode: strtoupper($currencyCode),
                amount: $topUpAmount,
                type: 'top_up',
                idempotencyKey: 'auto_top_up:'.$key,
                metadata: [
                    'source' => 'auto_top_up',
                    'auto_top_up' => true,
                    'payment_id' => $payment->id,
                    'reason' => 'automatic_wallet_top_up',
                ],
            );
        } catch (Throwable $exception) {
            $reason = $exception->getMessage() === 'payment_blocked'
                ? 'payment_risk_blocked'
                : ($exception->getMessage() ?: 'auto_top_up_failed');

            $this->recordActivity($user, 'billing.auto_top_up_failed', [
                'reason' => $reason,
                'amount' => $topUpAmount,
                'currency' => strtoupper($currencyCode),
            ]);

            return $this->blocked($reason, attempted: true);
        }

        $this->recordActivity($user, 'billing.auto_top_up_succeeded', [
            'payment_id' => $payment->id,
            'payment_uuid' => $payment->uuid,
            'wallet_transaction_id' => $walletTransaction->id,
            'amount' => $topUpAmount,
            'currency' => strtoupper($currencyCode),
        ]);

        return $this->allowed([
            'attempted' => true,
            'payment' => $payment,
            'wallet_transaction' => $walletTransaction,
            'top_up_amount' => $topUpAmount,
        ]);
    }

    private function limitReached(User $user, string $window, ?int $limit): bool
    {
        if ($limit === null) {
            return false;
        }

        $from = $window === 'day' ? now()->subDay() : now()->subMonth();

        $count = WalletTransaction::query()
            ->whereHas('wallet', fn ($query) => $query->where('user_id', $user->id))
            ->where('type', 'top_up')
            ->where('status', 'completed')
            ->where('metadata->source', 'auto_top_up')
            ->where('created_at', '>=', $from)
            ->count();

        return $count >= $limit;
    }

    private function activeCurrency(string $currencyCode): ?Currency
    {
        return Currency::query()
            ->where('code', strtoupper($currencyCode))
            ->where('is_active', true)
            ->first();
    }

    private function defaultPaymentMethod(User $user): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->first();
    }

    private function allowed(array $metadata = []): array
    {
        return [
            'allowed' => true,
            'attempted' => $metadata['attempted'] ?? false,
            'reason' => null,
            'payment' => $metadata['payment'] ?? null,
            'wallet_transaction' => $metadata['wallet_transaction'] ?? null,
            'metadata' => $metadata,
        ];
    }

    private function blocked(string $reason, array $metadata = [], bool $attempted = false): array
    {
        return [
            'allowed' => false,
            'attempted' => $attempted,
            'reason' => $reason,
            'payment' => null,
            'wallet_transaction' => null,
            'metadata' => $metadata,
        ];
    }

    private function recordActivity(User $user, string $action, array $metadata): void
    {
        try {
            $this->activityService->log($user->id, $action, 'Billing automation event', array_merge([
                'source' => 'auto_top_up_service',
                'module' => 'billing',
            ], $metadata));
        } catch (Throwable) {
            // Automation decisions must not fail because activity logging failed.
        }
    }
}
