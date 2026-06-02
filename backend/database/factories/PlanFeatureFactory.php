<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanFeature>
 */
class PlanFeatureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'feature_key' => fake()->randomElement([
                'chat.messages.daily',
                'chat.messages.monthly',
                'chat.conversations.active',
                'chat.webhook_endpoints.count',
                'dialer.calls.monthly',
                'platform.api_tokens.count',
            ]),
            'value' => (string) fake()->numberBetween(10, 1000),
            'value_type' => 'integer',
            'period' => 'monthly',
            'reset_policy' => 'calendar_month',
            'is_enabled' => true,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function boolean(): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_key' => 'chat.external_api.enabled',
            'value' => '1',
            'value_type' => 'boolean',
            'period' => 'none',
            'reset_policy' => 'none',
        ]);
    }
}

