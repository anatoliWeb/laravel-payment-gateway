<?php

namespace App\Services\Billing;

use App\DTO\Payments\CreatePaymentData;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPaymentPreference;
use App\Services\ActivityService;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Coordinates subscription lifecycle state changes and simulator-safe renewals.
 */
class SubscriptionLifecycleService
{
    private const ACTIVE_STATUSES = ['active', 'trialing'];

    private const FINAL_STATUSES = ['cancelled', 'expired'];

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentPreferenceService $paymentPreferenceService,
        private readonly AutoChargeService $autoChargeService,
        private readonly WalletService $walletService,
        private readonly ActivityService $activityService,
    ) {}

    /**
     * Create a pending subscription before payment settlement.
     *
     * @param array<string, mixed> $context
     */
    public function createPendingSubscription(User $user, Plan $plan, array $context = []): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $context): Subscription {
            $metadata = $this->sanitizeMetadata((array) ($context['metadata'] ?? []));
            $idempotencyHash = isset($context['idempotency_key'])
                ? hash('sha256', $user->id.'|'.$context['idempotency_key'].'|'.$plan->id)
                : null;

            if ($idempotencyHash !== null) {
                $existing = Subscription::query()
                    ->where('user_id', $user->id)
                    ->where('plan_id', $plan->id)
                    ->where('metadata->creation_idempotency_hash', $idempotencyHash)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $now = now();
            $subscription = Subscription::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'started_at' => null,
                'current_period_start' => null,
                'current_period_end' => null,
                'trial_ends_at' => $plan->trial_days > 0 ? $now->copy()->addDays((int) $plan->trial_days) : null,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'ended_at' => null,
                'metadata' => array_filter(array_merge($metadata, [
                    'source' => 'subscription_lifecycle_service',
                    'creation_idempotency_hash' => $idempotencyHash,
                    'created_from' => $context['source'] ?? 'subscription_lifecycle',
                ]), fn ($value) => $value !== null && $value !== []),
            ]);

            $this->recordActivity($subscription, 'billing.subscription_created', 'Billing subscription created');

            return $subscription->refresh();
        });
    }

    /**
     * Activate or renew a subscription only after a succeeded linked payment.
     */
    public function activateAfterPayment(Subscription $subscription, Payment $payment): Subscription
    {
        if ($payment->status !== 'succeeded') {
            throw new RuntimeException('subscription_activation_requires_succeeded_payment');
        }

        return DB::transaction(function () use ($subscription, $payment): Subscription {
            $locked = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, self::FINAL_STATUSES, true)) {
                return $locked->refresh();
            }

            if ($this->wasPaymentAlreadyApplied($locked, $payment)) {
                return $locked->refresh();
            }

            $periodStart = now();
            $periodEnd = $this->nextPeriodEnd($locked->plan, $periodStart);
            $metadata = $locked->metadata ?? [];
            $pendingChange = $metadata['pending_plan_change'] ?? null;

            if (is_array($pendingChange) && ($pendingChange['type'] ?? null) === 'upgrade') {
                $newPlan = Plan::query()->find($pendingChange['plan_id'] ?? null);
                if ($newPlan) {
                    $locked->plan_id = $newPlan->id;
                    unset($metadata['pending_plan_change']);
                    $metadata['last_plan_change'] = [
                        'type' => 'upgrade',
                        'plan_id' => $newPlan->id,
                        'payment_id' => $payment->id,
                        'applied_at' => now()->toISOString(),
                    ];
                }
            }

            // WHY: Activation is gated by persisted payment success so failed,
            // expired, or replayed non-successful attempts can never grant access.
            $locked->forceFill([
                'status' => 'active',
                'started_at' => $locked->started_at ?? $periodStart,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'ended_at' => null,
                'metadata' => array_merge($metadata, [
                    'activated_by_payment_id' => $payment->id,
                    'activated_at' => now()->toISOString(),
                    'last_successful_payment_id' => $payment->id,
                ]),
            ])->save();

            $this->recordActivity(
                $locked->refresh(),
                $this->isRenewalPayment($payment) ? 'billing.subscription_renewal_succeeded' : 'billing.subscription_activated',
                $this->isRenewalPayment($payment) ? 'Billing subscription renewed' : 'Billing subscription activated',
                $payment,
            );

            return $locked->refresh();
        });
    }

    /**
     * Record failed payment impact without activating access.
     */
    public function markPaymentFailed(Subscription $subscription, Payment $payment): Subscription
    {
        return DB::transaction(function () use ($subscription, $payment): Subscription {
            $locked = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, self::FINAL_STATUSES, true)) {
                return $locked->refresh();
            }

            $metadata = array_merge($locked->metadata ?? [], [
                'last_failed_payment_id' => $payment->id,
                'last_payment_failure_reason' => $payment->failure_reason,
                'last_payment_failed_at' => now()->toISOString(),
            ]);

            $newStatus = $locked->status;
            $action = 'billing.subscription_payment_failed';

            if ($this->isRenewalPayment($payment) && in_array($locked->status, self::ACTIVE_STATUSES, true)) {
                // WHY: Renewal failure should not delete access data; it moves
                // the subscription into a recoverable past_due state.
                $newStatus = 'past_due';
                $action = 'billing.subscription_past_due';
                $metadata['past_due_at'] = now()->toISOString();
            }

            $locked->forceFill([
                'status' => $newStatus,
                'metadata' => $metadata,
            ])->save();

            $this->recordActivity($locked->refresh(), $action, 'Billing subscription payment failed', $payment);

            return $locked->refresh();
        });
    }

    /**
     * Cancel a subscription immediately or at period end.
     */
    public function cancelSubscription(Subscription $subscription, User $actor, ?string $reason = null, bool $immediate = false): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor, $reason, $immediate): Subscription {
            $locked = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'expired') {
                return $locked->refresh();
            }

            $metadata = array_merge($locked->metadata ?? [], array_filter([
                'cancelled_by_user_id' => $actor->id,
                'cancellation_reason' => $reason,
            ]));

            $locked->forceFill([
                'status' => $immediate ? 'cancelled' : $locked->status,
                'cancel_at_period_end' => ! $immediate,
                'cancelled_at' => now(),
                'ended_at' => $immediate ? now() : $locked->ended_at,
                'metadata' => $metadata,
            ])->save();

            $this->recordActivity($locked->refresh(), 'billing.subscription_cancelled', 'Billing subscription cancelled');

            return $locked->refresh();
        });
    }

    /**
     * Expire a subscription when access has elapsed and no renewal succeeded.
     */
    public function expireSubscription(Subscription $subscription, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $reason): Subscription {
            $locked = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'expired') {
                return $locked->refresh();
            }

            $locked->forceFill([
                'status' => 'expired',
                'ended_at' => $locked->ended_at ?? now(),
                'metadata' => array_merge($locked->metadata ?? [], array_filter([
                    'expired_by' => 'subscription_lifecycle_service',
                    'expired_reason' => $reason,
                    'expired_at' => now()->toISOString(),
                ])),
            ])->save();

            $this->recordActivity($locked->refresh(), 'billing.subscription_expired', 'Billing subscription expired');

            return $locked->refresh();
        });
    }

    /**
     * Apply a basic upgrade immediately or schedule downgrade for period end.
     *
     * @param array<string, mixed> $options
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, User $actor, array $options = []): Subscription
    {
        return DB::transaction(function () use ($subscription, $newPlan, $actor, $options): Subscription {
            $locked = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, self::FINAL_STATUSES, true)) {
                throw new RuntimeException('subscription_status_is_final');
            }

            $direction = (string) ($options['direction'] ?? $this->planChangeDirection($locked->plan, $newPlan));
            $metadata = $locked->metadata ?? [];

            if ($direction === 'upgrade' && ($options['apply_immediately'] ?? true)) {
                $locked->forceFill([
                    'plan_id' => $newPlan->id,
                    'metadata' => array_merge($metadata, [
                        'last_plan_change' => [
                            'type' => 'upgrade',
                            'plan_id' => $newPlan->id,
                            'changed_by_user_id' => $actor->id,
                            'changed_at' => now()->toISOString(),
                            'proration' => 'not_implemented',
                        ],
                    ]),
                ])->save();

                $this->recordActivity($locked->refresh(), 'billing.subscription_plan_upgrade_requested', 'Billing subscription plan upgraded');

                return $locked->refresh();
            }

            // WHY: Pending plan changes are metadata-backed so Phase 19 avoids
            // schema churn and never grants unpaid upgrades before payment success.
            $metadata['pending_plan_change'] = [
                'type' => $direction,
                'plan_id' => $newPlan->id,
                'requested_by_user_id' => $actor->id,
                'effective_at' => $direction === 'downgrade'
                    ? $locked->current_period_end?->toISOString()
                    : null,
                'proration' => 'not_implemented',
            ];

            $locked->forceFill(['metadata' => $metadata])->save();
            $this->recordActivity(
                $locked->refresh(),
                $direction === 'upgrade'
                    ? 'billing.subscription_plan_upgrade_requested'
                    : 'billing.subscription_plan_downgrade_requested',
                $direction === 'upgrade'
                    ? 'Billing subscription upgrade requested'
                    : 'Billing subscription downgrade requested',
            );

            return $locked->refresh();
        });
    }

    /**
     * Attempt simulator-safe renewal using wallet or saved payment method consent.
     *
     * @return array{renewed: bool, attempted: bool, reason: string|null, payment: Payment|null, subscription: Subscription}
     */
    public function attemptRenewal(Subscription $subscription): array
    {
        $subscription = $subscription->fresh(['user', 'plan']);
        if (! $subscription) {
            throw new RuntimeException('subscription_not_found');
        }

        $this->recordActivity($subscription, 'billing.subscription_renewal_attempted', 'Billing subscription renewal attempted');

        if (! in_array($subscription->status, ['active', 'trialing', 'past_due'], true)) {
            return $this->renewalResult($subscription, false, false, 'subscription_not_renewable');
        }

        if ($subscription->status !== 'past_due'
            && $subscription->current_period_end !== null
            && $subscription->current_period_end->greaterThan(now())) {
            return $this->renewalResult($subscription, false, false, 'subscription_not_due');
        }

        $plan = $subscription->plan;
        if (! $plan) {
            return $this->markPastDueFromRenewalFailure($subscription, 'plan_not_available');
        }

        if ((int) $plan->price_amount <= 0) {
            return $this->renewedByFreePlan($subscription);
        }

        $user = $subscription->user;
        $preference = $this->paymentPreferenceService->getOrCreatePreferences($user);
        $idempotencyKey = $this->renewalIdempotencyKey($subscription);
        $metadata = [
            'source' => 'subscription_renewal',
            'subscription_renewal' => true,
            'subscription_id' => $subscription->id,
            'period_end' => $subscription->current_period_end?->toISOString(),
        ];

        if (in_array($preference->strategy, ['wallet_only', 'wallet_first'], true)
            && $this->walletHasEnoughBalance($user, $plan->currency, (int) $plan->price_amount)) {
            try {
                $payment = $this->paymentService->createPayment(new CreatePaymentData(
                    user: $user,
                    subscriptionId: $subscription->id,
                    planSlug: null,
                    amount: (int) $plan->price_amount,
                    currency: strtoupper($plan->currency),
                    paymentSource: 'wallet',
                    paymentStrategy: 'wallet_only',
                    paymentMethodId: null,
                    callbackUrl: null,
                    description: 'Subscription renewal from wallet balance',
                    metadata: $metadata,
                    idempotencyKey: $idempotencyKey,
                ));

                $renewed = $this->activateAfterPayment($subscription, $payment);

                return $this->renewalResult($renewed, true, true, null, $payment);
            } catch (Throwable $exception) {
                return $this->markPastDueFromRenewalFailure($subscription, $exception->getMessage() ?: 'wallet_renewal_failed');
            }
        }

        $permission = $this->autoChargeService->canAutoCharge($user);
        if (! $permission['allowed']) {
            if ($this->hasRenewalIntent($subscription, $preference)) {
                return $this->markPastDueFromRenewalFailure(
                    $subscription,
                    $permission['reason'] ?? 'renewal_payment_method_unavailable',
                );
            }

            return $this->renewalResult($subscription, false, false, $permission['reason'] ?? 'renewal_not_configured');
        }

        try {
            // WHY: Card renewal creates a simulator-safe payment attempt linked
            // to the subscription; actual access extension waits for success.
            $payment = $this->paymentService->createPayment(new CreatePaymentData(
                user: $user,
                subscriptionId: $subscription->id,
                planSlug: null,
                amount: (int) $plan->price_amount,
                currency: strtoupper($plan->currency),
                paymentSource: 'payment_method',
                paymentStrategy: 'payment_method_only',
                paymentMethodId: null,
                callbackUrl: null,
                description: 'Subscription automatic renewal charge',
                metadata: $metadata,
                idempotencyKey: $idempotencyKey,
            ));
        } catch (Throwable $exception) {
            return $this->markPastDueFromRenewalFailure($subscription, $exception->getMessage() ?: 'auto_charge_failed');
        }

        if ($payment->status === 'succeeded') {
            $renewed = $this->activateAfterPayment($subscription, $payment);

            return $this->renewalResult($renewed, true, true, null, $payment);
        }

        return $this->renewalResult($subscription->refresh(), false, true, 'renewal_payment_pending', $payment);
    }

    private function renewedByFreePlan(Subscription $subscription): array
    {
        $payment = new Payment(['status' => 'succeeded']);
        $renewed = DB::transaction(function () use ($subscription): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $start = now();

            $locked->forceFill([
                'status' => 'active',
                'current_period_start' => $start,
                'current_period_end' => $this->nextPeriodEnd($locked->plan, $start),
                'ended_at' => null,
                'metadata' => array_merge($locked->metadata ?? [], [
                    'last_free_renewal_at' => now()->toISOString(),
                ]),
            ])->save();

            $this->recordActivity($locked->refresh(), 'billing.subscription_renewal_succeeded', 'Billing subscription renewed');

            return $locked->refresh();
        });

        return $this->renewalResult($renewed, true, true, null, $payment);
    }

    private function markPastDueFromRenewalFailure(Subscription $subscription, string $reason): array
    {
        $pastDue = DB::transaction(function () use ($subscription, $reason): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'status' => 'past_due',
                'metadata' => array_merge($locked->metadata ?? [], [
                    'past_due_at' => now()->toISOString(),
                    'renewal_failure_reason' => $reason,
                ]),
            ])->save();

            $this->recordActivity($locked->refresh(), 'billing.subscription_renewal_failed', 'Billing subscription renewal failed');
            $this->recordActivity($locked->refresh(), 'billing.subscription_past_due', 'Billing subscription marked past due');

            return $locked->refresh();
        });

        return $this->renewalResult($pastDue, false, true, $reason);
    }

    private function walletHasEnoughBalance(User $user, string $currency, int $amount): bool
    {
        $balance = $this->walletService->getBalance($user, strtoupper($currency));

        return $balance !== null && $balance->available_amount >= $amount;
    }

    private function wasPaymentAlreadyApplied(Subscription $subscription, Payment $payment): bool
    {
        return (int) data_get($subscription->metadata, 'last_successful_payment_id') === (int) $payment->id
            || (int) data_get($subscription->metadata, 'activated_by_payment_id') === (int) $payment->id;
    }

    private function isRenewalPayment(Payment $payment): bool
    {
        return (bool) data_get($payment->metadata ?? [], 'subscription_renewal', false);
    }

    private function hasRenewalIntent(Subscription $subscription, UserPaymentPreference $preference): bool
    {
        return (bool) data_get($subscription->metadata ?? [], 'auto_renew', false)
            || $preference->auto_charge_enabled;
    }

    private function nextPeriodEnd(?Plan $plan, mixed $periodStart): mixed
    {
        $start = $periodStart instanceof \Illuminate\Support\Carbon ? $periodStart : now();

        return match ($plan?->billing_interval) {
            'yearly' => $start->copy()->addYearNoOverflow(),
            'weekly' => $start->copy()->addWeek(),
            'none' => $start->copy()->addMonthNoOverflow(),
            default => $start->copy()->addMonthNoOverflow(),
        };
    }

    private function planChangeDirection(?Plan $currentPlan, Plan $newPlan): string
    {
        if (! $currentPlan) {
            return 'upgrade';
        }

        return (int) $newPlan->price_amount >= (int) $currentPlan->price_amount
            ? 'upgrade'
            : 'downgrade';
    }

    private function renewalIdempotencyKey(Subscription $subscription): string
    {
        return 'subscription_renewal:'.hash('sha256', implode('|', [
            $subscription->id,
            $subscription->current_period_end?->toISOString() ?? 'no_period_end',
        ]));
    }

    private function renewalResult(
        Subscription $subscription,
        bool $renewed,
        bool $attempted,
        ?string $reason,
        ?Payment $payment = null,
    ): array {
        return [
            'renewed' => $renewed,
            'attempted' => $attempted,
            'reason' => $reason,
            'payment' => $payment,
            'subscription' => $subscription->refresh(),
        ];
    }

    private function recordActivity(Subscription $subscription, string $action, string $description, ?Payment $payment = null): void
    {
        try {
            $this->activityService->log($subscription->user_id, $action, $description, [
                'source' => 'subscription_lifecycle_service',
                'module' => 'billing',
                'subscription_id' => $subscription->id,
                'subscription_uuid' => $subscription->uuid,
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
                'payment_id' => $payment?->id,
                'payment_uuid' => $payment?->uuid,
            ]);
        } catch (Throwable) {
            // Subscription state changes must not fail because activity logging failed.
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
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
