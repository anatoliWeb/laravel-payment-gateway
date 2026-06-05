<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PaymentSimulationService
{
    private const FINAL_STATUSES = ['succeeded', 'failed', 'expired', 'cancelled'];

    private const SIMULATABLE_PROVIDERS = ['simulator', 'manual', 'internal_wallet'];

    private const ALLOWED_TRANSITIONS = [
        'pending' => ['processing', 'succeeded', 'failed', 'expired', 'cancelled'],
        'processing' => ['succeeded', 'failed', 'expired', 'cancelled'],
    ];

    private const FORBIDDEN_KEYS = [
        'card_number',
        'number',
        'pan',
        'cvv',
        'cvc',
        'security_code',
        'token',
        'secret',
        'password',
        'private_key',
        'provider_config',
        'credentials',
    ];

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly WebhookDeliveryService $webhookDeliveryService,
    ) {}

    public function simulateSuccess(Payment $payment, User $actor, array $metadata = []): Payment
    {
        return $this->transition($payment, $actor, 'succeeded', null, $metadata);
    }

    public function simulateFailure(Payment $payment, User $actor, string $reason, array $metadata = []): Payment
    {
        $reason = $this->normalizeReason($reason);

        return $this->transition($payment, $actor, 'failed', $reason, $metadata);
    }

    private function transition(
        Payment $payment,
        User $actor,
        string $targetStatus,
        ?string $reason,
        array $metadata,
    ): Payment {
        return DB::transaction(function () use ($payment, $actor, $targetStatus, $reason, $metadata): Payment {
            $locked = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertSimulatable($locked);

            if ($locked->status === $targetStatus && in_array($locked->status, self::FINAL_STATUSES, true)) {
                return $locked->refresh();
            }

            if (in_array($locked->status, self::FINAL_STATUSES, true)) {
                throw new RuntimeException('payment_already_final');
            }

            if (! in_array($targetStatus, self::ALLOWED_TRANSITIONS[$locked->status] ?? [], true)) {
                throw new RuntimeException('payment_invalid_transition');
            }

            $previousStatus = $locked->status;
            $safeMetadata = $this->sanitizeMetadata($metadata);

            $locked->status = $targetStatus;
            if ($targetStatus === 'succeeded') {
                $locked->paid_at = now();
            }

            if ($targetStatus === 'failed') {
                $locked->failed_at = now();
                $locked->failure_reason = $reason;
            }

            $locked->save();

            PaymentTransaction::query()->create([
                'payment_id' => $locked->id,
                'type' => $targetStatus === 'succeeded' ? 'payment_succeeded' : 'payment_failed',
                'status_from' => $previousStatus,
                'status_to' => $targetStatus,
                'amount' => $locked->amount,
                'currency' => $locked->currency,
                'message' => $targetStatus === 'succeeded'
                    ? 'Payment success simulated.'
                    : 'Payment failure simulated.',
                'payload' => array_filter([
                    'source' => 'payment_simulation_service',
                    'actor_id' => $actor->id,
                    'reason' => $reason,
                    'provider' => $locked->provider,
                    'provider_reference' => $locked->provider_reference,
                    'company_id' => $locked->company_id,
                    'seller_id' => $locked->seller_id,
                    'payer_user_id' => $locked->payer_user_id,
                    'metadata' => $safeMetadata,
                ], fn ($value) => $value !== null && $value !== []),
            ]);

            $this->recordActivity($locked, $actor, $previousStatus, $targetStatus, $reason, $safeMetadata);
            $this->recordWebhookDelivery($locked, $targetStatus, $safeMetadata);

            return $locked->refresh();
        });
    }

    private function assertSimulatable(Payment $payment): void
    {
        if (! in_array($payment->provider, self::SIMULATABLE_PROVIDERS, true)) {
            throw new RuntimeException('payment_not_simulatable');
        }
    }

    private function normalizeReason(string $reason): string
    {
        $reason = trim($reason);

        return preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $reason) === 1
            ? $reason
            : 'payment_simulation_failed';
    }

    private function recordActivity(
        Payment $payment,
        User $actor,
        string $previousStatus,
        string $targetStatus,
        ?string $reason,
        array $metadata,
    ): void {
        try {
            $this->activityService->log(
                $actor->id,
                $targetStatus === 'succeeded'
                    ? 'billing.payment_simulated_success'
                    : 'billing.payment_simulated_failure',
                $targetStatus === 'succeeded'
                    ? 'Billing payment success simulated'
                    : 'Billing payment failure simulated',
                array_filter([
                    'source' => 'payment_simulation_service',
                    'module' => 'billing',
                    'payment_id' => $payment->id,
                    'payment_uuid' => $payment->uuid,
                    'actor_id' => $actor->id,
                    'previous_status' => $previousStatus,
                    'new_status' => $targetStatus,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'reason' => $reason,
                    'provider' => $payment->provider,
                    'provider_reference' => $payment->provider_reference,
                    'company_id' => $payment->company_id,
                    'seller_id' => $payment->seller_id,
                    'payer_user_id' => $payment->payer_user_id,
                    'metadata' => $metadata,
                ], fn ($value) => $value !== null && $value !== []),
            );
        } catch (Throwable) {
            // Activity logging must not break payment state transitions.
        }
    }

    private function sanitizeMetadata(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN_KEYS, true)) {
                unset($metadata[$key]);

                continue;
            }

            if (is_array($value)) {
                $metadata[$key] = $this->sanitizeMetadata($value);
            }
        }

        return $metadata;
    }

    private function recordWebhookDelivery(Payment $payment, string $targetStatus, array $metadata): void
    {
        $eventType = match ($targetStatus) {
            'succeeded' => 'payment.succeeded',
            'failed' => 'payment.failed',
            default => null,
        };

        if ($eventType === null) {
            return;
        }

        $delivery = $this->webhookDeliveryService->createForPaymentEvent($payment, $eventType, [
            'source' => 'payment_simulation_service',
            'simulation_metadata' => $metadata,
        ]);

        if ($delivery !== null) {
            $this->webhookDeliveryService->dispatch($delivery);
        }
    }
}
