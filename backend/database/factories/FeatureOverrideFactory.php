<?php

namespace Database\Factories;

use App\Models\FeatureOverride;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureOverride>
 *
 * Generates feature override rows without invoking access services.
 */
class FeatureOverrideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => null,
            'feature_key' => 'chat.messages.daily',
            'value' => '1000',
            'value_type' => 'integer',
            'period' => 'daily',
            'reset_policy' => 'calendar_day',
            'is_enabled' => true,
            'priority' => 100,
            'reason' => 'manual_override',
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
            'created_by' => null,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function enabledFeature(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => '1',
            'value_type' => 'boolean',
            'period' => 'none',
            'reset_policy' => 'none',
            'is_enabled' => true,
        ]);
    }

    public function disabledFeature(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => '0',
            'value_type' => 'boolean',
            'period' => 'none',
            'reset_policy' => 'none',
            'is_enabled' => false,
        ]);
    }

    public function numericLimit(int $value = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => (string) $value,
            'value_type' => 'integer',
            'period' => 'daily',
            'reset_policy' => 'calendar_day',
            'is_enabled' => true,
        ]);
    }

    public function forFeature(string $featureKey): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_key' => $featureKey,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
    }

    // WHY: Subscription-level overrides intentionally outrank user-level rows
    // so temporary subscription exceptions can be scoped tightly.
    public function subscriptionLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'subscription_id' => Subscription::factory(),
            'priority' => 100,
        ]);
    }
}
