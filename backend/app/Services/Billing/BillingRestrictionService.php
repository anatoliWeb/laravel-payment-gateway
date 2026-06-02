<?php

namespace App\Services\Billing;

use App\Models\BillingRestriction;
use App\Models\User;

class BillingRestrictionService
{
    public function isBillingBlocked(User $user): bool
    {
        return $this->getActiveRestriction($user, 'billing_blocked') !== null;
    }

    public function isPaymentBlocked(User $user): bool
    {
        return $this->getActiveRestriction($user, 'payment_blocked') !== null;
    }

    public function isFeatureBlocked(User $user, string $featureKey): bool
    {
        return $this->getActiveRestriction($user, 'feature_blocked', $featureKey) !== null;
    }

    public function getActiveRestriction(User $user, string $type, ?string $featureKey = null): ?BillingRestriction
    {
        $now = now();

        $query = BillingRestriction::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $now);
            });

        if ($featureKey !== null) {
            $query->where('feature_key', $featureKey);
        }

        // WHY: Payment blocks are intentionally queried separately from billing
        // blocks so payment policy does not accidentally disable all feature access.
        return $query->latest('starts_at')
            ->latest('id')
            ->first();
    }
}
