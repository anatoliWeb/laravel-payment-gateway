<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 *
 * Generates disposable billing plans for model and relation tests.
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'slug' => 'test-plan-'.Str::lower(Str::random(8)),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'type' => 'paid',
            'price_amount' => 2900,
            'currency' => 'USD',
            'billing_interval' => 'monthly',
            'trial_days' => 0,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 100,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    // WHY: Factory states mirror seeded plans so tests can express intent
    // without depending on shared seeded records.
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'free-'.Str::lower(Str::random(8)),
            'name' => 'Free',
            'type' => 'free',
            'price_amount' => 0,
            'sort_order' => 10,
        ]);
    }

    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'basic-'.Str::lower(Str::random(8)),
            'name' => 'Basic',
            'type' => 'paid',
            'price_amount' => 2900,
            'sort_order' => 20,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'pro-'.Str::lower(Str::random(8)),
            'name' => 'Pro',
            'type' => 'paid',
            'price_amount' => 9900,
            'sort_order' => 30,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'enterprise-'.Str::lower(Str::random(8)),
            'name' => 'Enterprise',
            'type' => 'enterprise',
            'price_amount' => 29900,
            'sort_order' => 40,
        ]);
    }

    public function demoEnterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'demo-enterprise-'.Str::lower(Str::random(8)),
            'name' => 'Demo Enterprise',
            'type' => 'demo',
            'price_amount' => 0,
            'is_public' => false,
            'sort_order' => 50,
        ]);
    }
}
