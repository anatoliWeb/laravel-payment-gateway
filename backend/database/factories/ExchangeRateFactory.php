<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 *
 * Generates manual/simulated exchange rates without external API calls.
 */
class ExchangeRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'base_currency_id' => Currency::factory()->usd()->base(),
            'quote_currency_id' => Currency::factory()->eur(),
            'rate' => '0.92000000',
            'source' => 'manual',
            'valid_from' => now(),
            'valid_until' => null,
            'is_active' => true,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'valid_from' => now()->subMinute(),
            'valid_until' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'valid_from' => now()->subDays(2),
            'valid_until' => now()->subDay(),
        ]);
    }

    public function simulated(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'simulated',
            'metadata' => [
                'source' => 'factory',
                'mode' => 'simulated',
            ],
        ]);
    }
}
