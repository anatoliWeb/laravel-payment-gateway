<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentMethod>
 *
 * Generates simulator-safe payment methods without raw card data.
 */
class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'type' => 'fake_card',
            'provider' => 'simulator',
            'status' => 'active',
            'display_name' => 'Visa ending 4242',
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => now()->addYears(3)->year,
            'provider_reference' => 'sim_pm_'.Str::lower(Str::random(12)),
            'is_default' => false,
            'consent_given_at' => now(),
            'metadata' => [
                'source' => 'factory',
                'simulator_safe' => true,
            ],
        ];
    }

    public function fakeCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fake_card',
            'provider' => 'simulator',
            'display_name' => 'Visa ending 4242',
            'brand' => 'visa',
            'last4' => '4242',
        ]);
    }

    public function fakeManualInvoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fake_manual_invoice',
            'provider' => 'manual',
            'display_name' => 'Manual invoice',
            'brand' => null,
            'last4' => null,
            'exp_month' => null,
            'exp_year' => null,
        ]);
    }

    public function fakeWallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fake_wallet',
            'provider' => 'internal_wallet',
            'display_name' => 'Internal wallet balance',
            'brand' => 'wallet',
            'last4' => null,
            'exp_month' => null,
            'exp_year' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
        ]);
    }
}
