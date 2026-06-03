<?php

namespace App\Services\Payments;

use App\DTO\Payments\CreatePaymentData;
use App\Models\Payment;
use App\Services\ActivityService;
use App\Services\Billing\BillingRestrictionService;
use Illuminate\Support\Arr;
use Throwable;

class PaymentRiskService
{
    private const MAX_FAILED_PER_HOUR = 5;
    private const MAX_FAILED_PER_DAY = 20;
    private const MAX_ATTEMPTS_PER_HOUR = 20;
    private const MAX_ATTEMPTS_PER_DAY = 100;
    private const MAX_DEMO_AMOUNT = 1_000_000;
    private const MAX_METADATA_KEYS = 25;

    public function __construct(
        private readonly BillingRestrictionService $billingRestrictionService,
        private readonly ActivityService $activityService,
    ) {
    }

    /**
     * @return array{allowed: bool, reason: ?string, risk_flags: array<int, string>, metadata: array<string, mixed>}
     */
    public function checkBeforePaymentCreation(
        CreatePaymentData $data,
        ?int $resolvedAmount = null,
        ?string $resolvedCurrency = null,
        ?string $resolvedSource = null,
    ): array {
        $amount = $resolvedAmount ?? (int) $data->amount;
        $currency = $resolvedCurrency ?? $data->currency;
        $source = $resolvedSource ?? $data->paymentSource ?? $data->paymentStrategy;

        $checks = [
            $this->paymentBlacklistCheck($data),
            $this->failedAttemptLimitCheck($data),
            $this->creationAttemptLimitCheck($data),
            $this->demoAmountCheck($amount),
            $this->suspiciousActivityCheck($data),
        ];

        $flags = array_values(array_unique(array_merge(...array_column($checks, 'risk_flags'))));
        $blocked = collect($checks)->first(fn (array $check): bool => ! $check['allowed']);

        $result = [
            'allowed' => $blocked === null,
            'reason' => $blocked['reason'] ?? null,
            'risk_flags' => $flags,
            'metadata' => [
                'amount' => $amount,
                'currency' => $currency,
                'payment_source' => $source,
                'idempotency_key_hash' => hash('sha256', $data->idempotencyKey),
                'checks' => array_map(fn (array $check): array => [
                    'allowed' => $check['allowed'],
                    'reason' => $check['reason'],
                    'risk_flags' => $check['risk_flags'],
                ], $checks),
            ],
        ];

        if (! $result['allowed']) {
            $this->recordRiskActivity($data, 'billing.payment_risk_blocked', $result);
        }

        if (in_array('suspicious_activity', $flags, true)) {
            $this->recordRiskActivity($data, 'billing.payment_suspicious_attempt', $result);
        }

        return $result;
    }

    /**
     * @return array{allowed: bool, reason: ?string, risk_flags: array<int, string>}
     */
    private function paymentBlacklistCheck(CreatePaymentData $data): array
    {
        if ($this->billingRestrictionService->isPaymentBlocked($data->user)) {
            return $this->blocked('payment_blocked', ['payment_blacklist']);
        }

        return $this->allowed();
    }

    /**
     * @return array{allowed: bool, reason: ?string, risk_flags: array<int, string>}
     */
    private function failedAttemptLimitCheck(CreatePaymentData $data): array
    {
        $failedLastHour = Payment::query()
            ->where('user_id', $data->user->id)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $failedLastDay = Payment::query()
            ->where('user_id', $data->user->id)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($failedLastHour >= self::MAX_FAILED_PER_HOUR || $failedLastDay >= self::MAX_FAILED_PER_DAY) {
            return $this->blocked('too_many_failed_attempts', ['failed_attempt_limit']);
        }

        return $this->allowed();
    }

    /**
     * @return array{allowed: bool, reason: ?string, risk_flags: array<int, string>}
     */
    private function creationAttemptLimitCheck(CreatePaymentData $data): array
    {
        $attemptsLastHour = Payment::query()
            ->where('user_id', $data->user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $attemptsLastDay = Payment::query()
            ->where('user_id', $data->user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($attemptsLastHour >= self::MAX_ATTEMPTS_PER_HOUR || $attemptsLastDay >= self::MAX_ATTEMPTS_PER_DAY) {
            return $this->blocked('too_many_payment_attempts', ['payment_attempt_limit']);
        }

        return $this->allowed();
    }

    /**
     * @return array{allowed: bool, reason: ?string, risk_flags: array<int, string>}
     */
    private function demoAmountCheck(int $amount): array
    {
        if ($amount > self::MAX_DEMO_AMOUNT) {
            return $this->blocked('amount_exceeds_demo_limit', ['demo_amount_limit']);
        }

        return $this->allowed();
    }

    /**
     * @return array{allowed: bool, reason: ?string, risk_flags: array<int, string>}
     */
    private function suspiciousActivityCheck(CreatePaymentData $data): array
    {
        $metadataKeyCount = count(Arr::dot($data->metadata));

        if ($metadataKeyCount > self::MAX_METADATA_KEYS) {
            return $this->blocked('suspicious_activity', ['suspicious_activity', 'large_metadata']);
        }

        return $this->allowed();
    }

    /**
     * @return array{allowed: bool, reason: ?string, risk_flags: array<int, string>}
     */
    private function allowed(): array
    {
        return [
            'allowed' => true,
            'reason' => null,
            'risk_flags' => [],
        ];
    }

    /**
     * @return array{allowed: bool, reason: string, risk_flags: array<int, string>}
     */
    private function blocked(string $reason, array $flags): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'risk_flags' => $flags,
        ];
    }

    /**
     * @param array{allowed: bool, reason: ?string, risk_flags: array<int, string>, metadata: array<string, mixed>} $result
     */
    private function recordRiskActivity(CreatePaymentData $data, string $action, array $result): void
    {
        try {
            $this->activityService->log($data->user->id, $action, 'Billing payment risk check blocked or flagged an attempt', [
                'source' => 'payment_risk_service',
                'module' => 'billing',
                'reason' => $result['reason'],
                'risk_flags' => $result['risk_flags'],
                'amount' => $result['metadata']['amount'],
                'currency' => $result['metadata']['currency'],
                'payment_source' => $result['metadata']['payment_source'],
                'payment_method_id' => $data->paymentMethodId,
            ]);
        } catch (Throwable) {
            // Risk logging must not change the payment decision outcome.
        }
    }
}
