<?php

namespace App\Services\Billing;

use App\Models\FeatureUsage;
use App\Models\PlanFeature;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UsageLimitService
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly SubscriptionService $subscriptionService,
    ) {
    }

    /**
     * @return array{allowed: bool, feature_key: string, used: int, limit: int|null, remaining: int|null, period: string|null, reset_at: Carbon|null, reason: string|null}
     */
    public function checkUsageLimit(User $user, string $featureKey, int $amount = 1): array
    {
        $context = $this->resolveNumericFeatureContext($user, $featureKey);

        if (! $context) {
            return $this->result(false, $featureKey, 0, null, null, null, 'feature_not_available');
        }

        $feature = $context['feature'];
        $limit = (int) $feature->value;

        if ($limit <= 0) {
            return $this->result(false, $featureKey, 0, $limit, 0, $feature->period, 'limit_not_available');
        }

        $window = $this->resolvePeriodWindow($feature->period);

        if (! $window) {
            return $this->result(false, $featureKey, 0, $limit, 0, $feature->period, 'unsupported_period');
        }

        $used = (int) FeatureUsage::query()
            ->where('user_id', $user->id)
            ->where('feature_key', $featureKey)
            ->where('period', $feature->period)
            ->where('period_start', $window['period_start'])
            ->where('period_end', $window['period_end'])
            ->value('used');

        $allowed = $used + $amount <= $limit;
        $remaining = max(0, $limit - $used);

        return $this->result(
            $allowed,
            $featureKey,
            $used,
            $limit,
            $remaining,
            $feature->period,
            $allowed ? null : 'limit_exceeded',
            $window['reset_at']
        );
    }

    public function incrementUsage(User $user, string $featureKey, int $amount = 1): FeatureUsage
    {
        return DB::transaction(function () use ($user, $featureKey, $amount): FeatureUsage {
            $context = $this->resolveNumericFeatureContext($user, $featureKey);

            if (! $context) {
                throw new \RuntimeException('feature_not_available');
            }

            $feature = $context['feature'];
            $limit = (int) $feature->value;
            $window = $this->resolvePeriodWindow($feature->period);

            if (! $window) {
                throw new \RuntimeException('unsupported_period');
            }

            $usage = FeatureUsage::query()
                ->where('user_id', $user->id)
                ->where('feature_key', $featureKey)
                ->where('period', $feature->period)
                ->where('period_start', $window['period_start'])
                ->where('period_end', $window['period_end'])
                ->lockForUpdate()
                ->first();

            $used = (int) ($usage?->used ?? 0);
            if ($limit <= 0 || $used + $amount > $limit) {
                throw new \RuntimeException('limit_exceeded');
            }

            // WHY: Usage increments are centralized here to avoid scattered
            // limit mutation logic across chat and future dialer modules.
            if (! $usage) {
                return FeatureUsage::query()->create([
                    'user_id' => $user->id,
                    'subscription_id' => $context['subscription']?->id,
                    'plan_id' => $context['plan']->id,
                    'feature_key' => $featureKey,
                    'period' => $feature->period,
                    'period_start' => $window['period_start'],
                    'period_end' => $window['period_end'],
                    'used' => $amount,
                    'limit_value' => $limit,
                    'reset_at' => $window['reset_at'],
                    'metadata' => ['source' => 'usage_limit_service'],
                ]);
            }

            $usage->forceFill([
                'used' => $used + $amount,
                'limit_value' => $limit,
                'subscription_id' => $context['subscription']?->id,
                'plan_id' => $context['plan']->id,
                'reset_at' => $window['reset_at'],
            ])->save();

            return $usage->refresh();
        });
    }

    public function resetUsageByPeriod(string $period, ?string $featureKey = null): int
    {
        $query = FeatureUsage::query()
            ->where('period', $period);

        if ($featureKey !== null) {
            $query->where('feature_key', $featureKey);
        }

        return $query->update(['used' => 0]);
    }

    /**
     * @return array{feature: PlanFeature, plan: \App\Models\Plan, subscription: \App\Models\Subscription|null}|null
     */
    private function resolveNumericFeatureContext(User $user, string $featureKey): ?array
    {
        $subscription = $this->subscriptionService->getCurrentSubscription($user);
        $plan = $subscription?->plan ?? $this->subscriptionService->getEffectivePlan($user);

        if (! $plan) {
            return null;
        }

        $feature = $this->planService->getEnabledFeature($plan, $featureKey);

        if (! $feature || ! in_array($feature->value_type, ['integer', 'decimal'], true)) {
            return null;
        }

        return [
            'feature' => $feature,
            'plan' => $plan,
            'subscription' => $subscription,
        ];
    }

    /**
     * @return array{period_start: Carbon, period_end: Carbon, reset_at: Carbon|null}|null
     */
    private function resolvePeriodWindow(string $period): ?array
    {
        $now = now();

        return match ($period) {
            'daily' => [
                'period_start' => $now->copy()->startOfDay(),
                'period_end' => $now->copy()->endOfDay(),
                'reset_at' => $now->copy()->addDay()->startOfDay(),
            ],
            'monthly' => [
                'period_start' => $now->copy()->startOfMonth(),
                'period_end' => $now->copy()->endOfMonth(),
                'reset_at' => $now->copy()->addMonthNoOverflow()->startOfMonth(),
            ],
            'none', 'lifetime' => [
                'period_start' => Carbon::create(1970, 1, 1, 0, 0, 0),
                'period_end' => Carbon::create(9999, 12, 31, 23, 59, 59),
                'reset_at' => null,
            ],
            // WHY: Billing-cycle windows need subscription renewal orchestration,
            // so Phase 10 safely reuses monthly windows until lifecycle services exist.
            'billing_cycle' => [
                'period_start' => $now->copy()->startOfMonth(),
                'period_end' => $now->copy()->endOfMonth(),
                'reset_at' => $now->copy()->addMonthNoOverflow()->startOfMonth(),
            ],
            default => null,
        };
    }

    /**
     * @return array{allowed: bool, feature_key: string, used: int, limit: int|null, remaining: int|null, period: string|null, reset_at: Carbon|null, reason: string|null}
     */
    private function result(
        bool $allowed,
        string $featureKey,
        int $used,
        ?int $limit,
        ?int $remaining,
        ?string $period,
        ?string $reason,
        ?Carbon $resetAt = null,
    ): array {
        return [
            'allowed' => $allowed,
            'feature_key' => $featureKey,
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'period' => $period,
            'reset_at' => $resetAt,
            'reason' => $reason,
        ];
    }
}
