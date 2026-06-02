<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Collection;

class PlanService
{
    public function getDefaultFreePlan(): ?Plan
    {
        return $this->findBySlug('free');
    }

    public function findBySlug(string $slug): ?Plan
    {
        return Plan::query()
            ->bySlug($slug)
            ->first();
    }

    /**
     * @return Collection<int, Plan>
     */
    public function getActivePublicPlans(): Collection
    {
        return Plan::query()
            ->active()
            ->public()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getFeature(Plan $plan, string $featureKey): ?PlanFeature
    {
        return $plan->features()
            ->where('feature_key', $featureKey)
            ->orderByRaw("CASE WHEN period = 'none' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    public function getEnabledFeature(Plan $plan, string $featureKey): ?PlanFeature
    {
        return $plan->features()
            ->where('feature_key', $featureKey)
            ->where('is_enabled', true)
            ->orderByRaw("CASE WHEN period = 'none' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    public function getFeatureValue(Plan $plan, string $featureKey, mixed $default = null): mixed
    {
        $feature = $this->getEnabledFeature($plan, $featureKey);

        if (! $feature) {
            return $default;
        }

        return $this->castFeatureValue($feature);
    }

    public function castFeatureValue(PlanFeature $feature): mixed
    {
        return match ($feature->value_type) {
            'boolean' => filter_var($feature->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $feature->value,
            'decimal' => (float) $feature->value,
            'json' => json_decode((string) $feature->value, true),
            default => $feature->value,
        };
    }
}
