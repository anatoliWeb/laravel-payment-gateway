<?php

namespace App\Services\Billing;

use App\Models\FeatureOverride;
use App\Models\Subscription;
use App\Models\User;

class FeatureOverrideService
{
    public function getActiveOverride(
        User $user,
        ?Subscription $subscription,
        string $featureKey,
        ?string $period = null,
    ): ?FeatureOverride {
        $now = now();

        $query = FeatureOverride::query()
            ->where('feature_key', $featureKey)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $now);
            });

        if ($period !== null) {
            $query->where('period', $period);
        }

        $query->where(function ($query) use ($user, $subscription): void {
            $query->where('user_id', $user->id);

            if ($subscription) {
                $query->orWhere('subscription_id', $subscription->id);
            }
        });

        // WHY: Subscription-level overrides beat user-level overrides so scoped
        // commercial exceptions do not unintentionally affect other subscriptions.
        if ($subscription) {
            $query->orderByRaw(
                'CASE WHEN subscription_id = ? THEN 0 ELSE 1 END',
                [$subscription->id]
            );
        }

        return $query->orderByDesc('priority')
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    public function getOverrideValue(
        User $user,
        ?Subscription $subscription,
        string $featureKey,
        mixed $default = null,
    ): mixed {
        $override = $this->getActiveOverride($user, $subscription, $featureKey);

        if (! $override) {
            return $default;
        }

        return $this->castOverrideValue($override);
    }

    public function hasOverride(
        User $user,
        ?Subscription $subscription,
        string $featureKey,
        ?string $period = null,
    ): bool {
        return $this->getActiveOverride($user, $subscription, $featureKey, $period) !== null;
    }

    public function castOverrideValue(FeatureOverride $override): mixed
    {
        return match ($override->value_type) {
            'boolean' => filter_var($override->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $override->value,
            'decimal' => (float) $override->value,
            'json' => json_decode((string) $override->value, true),
            default => $override->value,
        };
    }
}
