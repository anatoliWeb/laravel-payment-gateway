<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $periodStart = now()->startOfDay();

        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'started_at' => $periodStart,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodStart->copy()->addMonth(),
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'cancel_at_period_end' => false,
            'ended_at' => null,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'started_at' => null,
        ]);
    }

    public function cancelledAtPeriodEnd(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancel_at_period_end' => true,
            'cancelled_at' => now(),
        ]);
    }
}

