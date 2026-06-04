<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 *
 * Generates valid persisted payment rows for tests without provider side effects.
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'payer_user_id' => fn (array $attributes) => $attributes['user_id'],
            'company_id' => null,
            'seller_id' => null,
            'provider_account_id' => null,
            'subscription_id' => fn (array $attributes) => Subscription::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
            'invoice_id' => null,
            'parent_payment_id' => null,
            'amount' => 2900,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'sim_'.Str::lower(Str::random(12)),
            'description' => 'Factory payment attempt',
            'failure_reason' => null,
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'source' => 'factory',
            ],
            'ownership_metadata' => [
                'scope' => 'user',
            ],
            'paid_at' => null,
            'failed_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failure_reason' => 'card_declined',
            'failed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expired_at' => now(),
        ]);
    }

    // WHY: Factory states create valid persistence records without triggering
    // provider behavior, retries, or webhook side effects.
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
