<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'wallet_id' => Wallet::factory(),
            'wallet_balance_id' => null,
            'currency_id' => Currency::factory()->usd()->base(),
            'payment_id' => null,
            'subscription_id' => null,
            'type' => 'top_up',
            'direction' => 'credit',
            'amount' => 1000,
            'balance_available_before' => 0,
            'balance_available_after' => 1000,
            'balance_held_before' => 0,
            'balance_held_after' => 0,
            'idempotency_key' => null,
            'reference_type' => null,
            'reference_id' => null,
            'reason' => 'factory_transaction',
            'status' => 'completed',
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function forBalance(WalletBalance $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'wallet_id' => $balance->wallet_id,
            'wallet_balance_id' => $balance->id,
            'currency_id' => $balance->currency_id,
        ]);
    }

    public function topUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'top_up',
            'direction' => 'credit',
        ]);
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
            'direction' => 'debit',
        ]);
    }

    public function hold(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'hold',
            'direction' => 'neutral',
        ]);
    }

    public function release(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'release',
            'direction' => 'neutral',
        ]);
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'refund',
            'direction' => 'credit',
        ]);
    }

    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'adjustment',
            'direction' => 'neutral',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }
}
