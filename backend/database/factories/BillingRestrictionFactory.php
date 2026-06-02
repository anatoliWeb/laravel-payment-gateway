<?php

namespace Database\Factories;

use App\Models\BillingRestriction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingRestriction>
 *
 * Generates manual restriction records without triggering billing side effects.
 */
class BillingRestrictionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'billing_blocked',
            'scope' => 'billing',
            'feature_key' => null,
            'reason' => 'manual_review',
            'is_active' => true,
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
            'created_by' => null,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function billingBlocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'billing_blocked',
            'scope' => 'billing',
            'feature_key' => null,
        ]);
    }

    public function paymentBlocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'payment_blocked',
            'scope' => 'payment',
            'feature_key' => null,
        ]);
    }

    public function featureBlocked(string $featureKey = 'chat.messages.daily'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'feature_blocked',
            'scope' => 'feature',
            'feature_key' => $featureKey,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    // WHY: Expired restrictions let tests prove temporary manual blocks do not
    // leak past their intended window.
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
    }
}
