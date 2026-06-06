<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionService
{
    private const ACTIVE_STATUSES = ['active', 'trialing'];

    public function __construct(
        private readonly PlanService $planService,
    ) {
    }

    public function getCurrentSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->with('plan')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('current_period_start')
            ->latest('id')
            ->first();
    }

    public function hasActiveSubscription(User $user): bool
    {
        return $user->subscriptions()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();
    }

    /**
     * Return the latest subscription regardless of access status.
     */
    public function getLatestSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->with('plan')
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    public function getCurrentPlan(User $user): ?Plan
    {
        return $this->getCurrentSubscription($user)?->plan;
    }

    public function getEffectivePlan(User $user): ?Plan
    {
        $currentPlan = $this->getCurrentPlan($user);

        if ($currentPlan) {
            return $currentPlan;
        }

        // WHY: Users without an active paid subscription fall back to the
        // seeded free plan so feature checks can remain simple and safe.
        return $this->planService->getDefaultFreePlan();
    }
}
