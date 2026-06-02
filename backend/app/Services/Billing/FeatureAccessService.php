<?php

namespace App\Services\Billing;

use App\Models\User;

class FeatureAccessService
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly SubscriptionService $subscriptionService,
        private readonly UsageLimitService $usageLimitService,
    ) {
    }

    public function canAccess(User $user, string $featureKey): bool
    {
        return (bool) $this->checkFeatureAvailability($user, $featureKey)['allowed'];
    }

    /**
     * @return array{allowed: bool, feature_key: string, value: mixed, reason: string|null, plan_slug: string|null}
     */
    public function checkFeatureAvailability(User $user, string $featureKey): array
    {
        $plan = $this->subscriptionService->getEffectivePlan($user);

        if (! $plan) {
            return $this->result(false, $featureKey, null, 'plan_not_available', null);
        }

        $feature = $this->planService->getEnabledFeature($plan, $featureKey);

        if (! $feature) {
            return $this->result(false, $featureKey, null, 'feature_not_available', $plan->slug);
        }

        $value = $this->planService->castFeatureValue($feature);

        if ($feature->value_type === 'boolean') {
            return $this->result((bool) $value, $featureKey, $value, (bool) $value ? null : 'feature_disabled', $plan->slug);
        }

        if (in_array($feature->value_type, ['integer', 'decimal'], true)) {
            $usage = $this->usageLimitService->checkUsageLimit($user, $featureKey, 0);

            return $this->result((bool) $usage['allowed'], $featureKey, $value, $usage['reason'], $plan->slug);
        }

        return $this->result(true, $featureKey, $value, null, $plan->slug);
    }

    public function getFeatureValue(User $user, string $featureKey, mixed $default = null): mixed
    {
        $plan = $this->subscriptionService->getEffectivePlan($user);

        if (! $plan) {
            return $default;
        }

        return $this->planService->getFeatureValue($plan, $featureKey, $default);
    }

    /**
     * @return array{allowed: bool, feature_key: string, value: mixed, reason: string|null, plan_slug: string|null}
     */
    private function result(bool $allowed, string $featureKey, mixed $value, ?string $reason, ?string $planSlug): array
    {
        // WHY: Feature keys are module-agnostic so chat and future dialer
        // modules can reuse this service without billing-specific branching.
        return [
            'allowed' => $allowed,
            'feature_key' => $featureKey,
            'value' => $value,
            'reason' => $reason,
            'plan_slug' => $planSlug,
        ];
    }
}
