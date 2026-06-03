<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Wallet;
use App\Models\WalletBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletBalance>
 */
class WalletBalanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'currency_id' => Currency::factory()->usd()->base(),
            'available_amount' => 10000,
            'held_amount' => 0,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
