<?php

namespace App\Services\Billing;

use App\DTO\Payments\CreatePaymentData;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Str;
use Throwable;

class AutoChargeService
{
    public function __construct(
        private readonly PaymentPreferenceService $paymentPreferenceService,
        private readonly PaymentService $paymentService,
        private readonly ActivityService $activityService,
    ) {
    }

    public function canAutoCharge(User $user): array
    {
        $preference = $this->paymentPreferenceService->getOrCreatePreferences($user);

        if (! $preference->auto_charge_enabled) {
            return $this->blocked('auto_charge_disabled');
        }

        if (! $preference->auto_charge_consent_at) {
            return $this->blocked('auto_charge_consent_missing');
        }

        $paymentMethod = $this->defaultPaymentMethod($user);
        if (! $paymentMethod) {
            return $this->blocked('payment_method_not_found');
        }

        if ($paymentMethod->status !== 'active') {
            return $this->blocked('payment_method_inactive');
        }

        return $this->allowed([
            'payment_method_id' => $paymentMethod->id,
        ]);
    }

    public function chargeWithDefaultMethod(
        User $user,
        int $amount,
        string $currencyCode,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): array {
        $this->recordActivity($user, 'billing.auto_charge_attempted', [
            'amount' => $amount,
            'currency' => strtoupper($currencyCode),
        ]);

        if ($amount <= 0) {
            $this->recordActivity($user, 'billing.auto_charge_failed', ['reason' => 'invalid_amount']);
            return $this->blocked('invalid_amount', attempted: true);
        }

        if (! $this->activeCurrency($currencyCode)) {
            $this->recordActivity($user, 'billing.auto_charge_failed', ['reason' => 'invalid_currency']);
            return $this->blocked('invalid_currency', attempted: true);
        }

        $permission = $this->canAutoCharge($user);
        if (! $permission['allowed']) {
            $action = $permission['reason'] === 'auto_charge_consent_missing'
                ? 'billing.auto_charge_consent_required'
                : 'billing.auto_charge_failed';
            $this->recordActivity($user, $action, ['reason' => $permission['reason']]);

            return array_merge($permission, ['attempted' => true]);
        }

        try {
            $payment = $this->paymentService->createPayment(new CreatePaymentData(
                user: $user,
                subscriptionId: null,
                planSlug: null,
                amount: $amount,
                currency: strtoupper($currencyCode),
                paymentSource: 'payment_method',
                paymentStrategy: 'payment_method_only',
                paymentMethodId: null,
                callbackUrl: null,
                description: 'Automatic simulator charge',
                metadata: array_merge($metadata, [
                    'source' => 'auto_charge',
                    'auto_charge' => true,
                ]),
                idempotencyKey: $idempotencyKey ?? 'auto_charge_'.Str::uuid(),
            ));
        } catch (Throwable $exception) {
            $reason = $this->normalizePaymentFailure($exception->getMessage(), 'auto_charge_failed');
            $this->recordActivity($user, 'billing.auto_charge_failed', [
                'reason' => $reason,
                'amount' => $amount,
                'currency' => strtoupper($currencyCode),
            ]);

            return $this->blocked($reason, attempted: true);
        }

        $this->recordActivity($user, 'billing.auto_charge_succeeded', [
            'payment_id' => $payment->id,
            'payment_uuid' => $payment->uuid,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ]);

        return $this->allowed([
            'attempted' => true,
            'payment' => $payment,
        ]);
    }

    private function defaultPaymentMethod(User $user): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->first();
    }

    private function activeCurrency(string $currencyCode): ?Currency
    {
        return Currency::query()
            ->where('code', strtoupper($currencyCode))
            ->where('is_active', true)
            ->first();
    }

    private function allowed(array $metadata = []): array
    {
        return [
            'allowed' => true,
            'attempted' => $metadata['attempted'] ?? false,
            'reason' => null,
            'payment' => $metadata['payment'] ?? null,
            'wallet_transaction' => null,
            'metadata' => $metadata,
        ];
    }

    private function blocked(string $reason, bool $attempted = false): array
    {
        return [
            'allowed' => false,
            'attempted' => $attempted,
            'reason' => $reason,
            'payment' => null,
            'wallet_transaction' => null,
            'metadata' => [],
        ];
    }

    private function normalizePaymentFailure(string $reason, string $fallback): string
    {
        return match ($reason) {
            'payment_blocked' => 'payment_risk_blocked',
            default => $reason !== '' ? $reason : $fallback,
        };
    }

    private function recordActivity(User $user, string $action, array $metadata): void
    {
        try {
            $this->activityService->log($user->id, $action, 'Billing automation event', array_merge([
                'source' => 'auto_charge_service',
                'module' => 'billing',
            ], $metadata));
        } catch (Throwable) {
            // Automation decisions must not fail because activity logging failed.
        }
    }
}
