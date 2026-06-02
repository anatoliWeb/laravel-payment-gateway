<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $currencies = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_precision' => 2,
                'is_active' => true,
                'is_base' => true,
                'description' => 'Base currency for SaaS billing and simulated exchange rates.',
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => 'EUR',
                'decimal_precision' => 2,
                'is_active' => true,
                'is_base' => false,
                'description' => 'Active European currency for multi-currency billing demos.',
            ],
            [
                'code' => 'UAH',
                'name' => 'Ukrainian Hryvnia',
                'symbol' => 'UAH',
                'decimal_precision' => 2,
                'is_active' => true,
                'is_base' => false,
                'description' => 'Active Ukrainian currency for regional billing demos.',
            ],
            [
                'code' => 'PLN',
                'name' => 'Polish Zloty',
                'symbol' => 'PLN',
                'decimal_precision' => 2,
                'is_active' => true,
                'is_base' => false,
                'description' => 'Active Polish currency for regional billing demos.',
            ],
            [
                'code' => 'GBP',
                'name' => 'British Pound Sterling',
                'symbol' => 'GBP',
                'decimal_precision' => 2,
                'is_active' => true,
                'is_base' => false,
                'description' => 'Active British currency for international billing demos.',
            ],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $currency['code']],
                array_merge($currency, [
                    'metadata' => json_encode(['seeded_by' => 'CurrencySeeder']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        }

        DB::table('currencies')->update(['is_base' => false, 'updated_at' => $now]);
        DB::table('currencies')->where('code', 'USD')->update(['is_base' => true, 'updated_at' => $now]);

        $currencyIds = DB::table('currencies')
            ->whereIn('code', array_column($currencies, 'code'))
            ->pluck('id', 'code')
            ->all();

        $validFrom = '2026-01-01 00:00:00';
        $rates = [
            ['base' => 'USD', 'quote' => 'EUR', 'rate' => '0.92000000'],
            ['base' => 'USD', 'quote' => 'UAH', 'rate' => '40.00000000'],
            ['base' => 'USD', 'quote' => 'PLN', 'rate' => '4.00000000'],
            ['base' => 'USD', 'quote' => 'GBP', 'rate' => '0.79000000'],
        ];

        foreach ($rates as $rate) {
            DB::table('exchange_rates')->updateOrInsert(
                [
                    'base_currency_id' => $currencyIds[$rate['base']],
                    'quote_currency_id' => $currencyIds[$rate['quote']],
                    'source' => 'manual',
                    'valid_from' => $validFrom,
                ],
                [
                    'rate' => $rate['rate'],
                    'valid_until' => null,
                    'is_active' => true,
                    'metadata' => json_encode([
                        'seeded_by' => 'CurrencySeeder',
                        'mode' => 'manual_simulated',
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
