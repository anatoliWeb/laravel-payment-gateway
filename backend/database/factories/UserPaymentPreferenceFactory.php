<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\UserPaymentPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPaymentPreference>
 *
 * Generates payment preferences without executing payment or top-up flows.
 */
class UserPaymentPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'default_payment_method_id' => null,
            'strategy' => 'wallet_first',
            'auto_charge_enabled' => false,
            'auto_top_up_enabled' => false,
            'auto_top_up_threshold_amount' => null,
            'auto_top_up_amount' => null,
            'auto_top_up_currency_id' => null,
            'max_auto_top_up_per_day' => null,
            'max_auto_top_up_per_month' => null,
            'auto_charge_consent_at' => null,
            'auto_top_up_consent_at' => null,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function withDefaultPaymentMethod(?PaymentMethod $paymentMethod = null): static
    {
        return $this->state(fn (array $attributes) => [
            'default_payment_method_id' => $paymentMethod?->id ?? PaymentMethod::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
        ]);
    }

    public function walletOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'strategy' => 'wallet_only',
        ]);
    }

    public function paymentMethodOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'strategy' => 'payment_method_only',
        ]);
    }

    public function walletFirst(): static
    {
        return $this->state(fn (array $attributes) => [
            'strategy' => 'wallet_first',
        ]);
    }

    public function manualInvoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'strategy' => 'manual_invoice',
        ]);
    }

    public function autoChargeEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_charge_enabled' => true,
            'auto_charge_consent_at' => now(),
        ]);
    }

    public function autoTopUpEnabled(?Currency $currency = null): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_top_up_enabled' => true,
            'auto_top_up_threshold_amount' => 1000,
            'auto_top_up_amount' => 5000,
            'auto_top_up_currency_id' => $currency?->id ?? Currency::factory()->usd()->create()->id,
            'max_auto_top_up_per_day' => 2,
            'max_auto_top_up_per_month' => 10,
            'auto_top_up_consent_at' => now(),
        ]);
    }
}
