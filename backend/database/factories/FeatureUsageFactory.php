<?php

namespace Database\Factories;

use App\Models\FeatureUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureUsage>
 */
class FeatureUsageFactory extends Factory
{
    public function definition(): array
    {
        $periodStart = now()->startOfDay();

        return [
            'user_id' => User::factory(),
            'subscription_id' => Subscription::factory(),
            'plan_id' => Plan::factory(),
            'feature_key' => 'chat.messages.monthly',
            'period' => 'monthly',
            'period_start' => $periodStart,
            'period_end' => $periodStart->copy()->addMonth(),
            'used' => 25,
            'limit_value' => 1000,
            'reset_at' => $periodStart->copy()->addMonth(),
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}

