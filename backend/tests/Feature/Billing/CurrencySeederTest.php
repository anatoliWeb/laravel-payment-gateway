<?php

namespace Tests\Feature\Billing;

use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrencySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_currencies_and_exchange_rates_exist(): void
    {
        $this->seed(CurrencySeeder::class);

        foreach (['USD', 'EUR', 'UAH', 'PLN', 'GBP'] as $code) {
            $this->assertDatabaseHas('currencies', [
                'code' => $code,
                'is_active' => true,
            ]);
        }

        $this->assertSame(1, DB::table('currencies')->where('is_base', true)->count());
        $this->assertDatabaseHas('currencies', [
            'code' => 'USD',
            'is_base' => true,
        ]);

        $usdId = DB::table('currencies')->where('code', 'USD')->value('id');
        $eurId = DB::table('currencies')->where('code', 'EUR')->value('id');

        $this->assertDatabaseHas('exchange_rates', [
            'base_currency_id' => $usdId,
            'quote_currency_id' => $eurId,
            'source' => 'manual',
            'is_active' => true,
        ]);
    }

    public function test_currency_seeder_is_idempotent(): void
    {
        $this->seed(CurrencySeeder::class);
        $this->seed(CurrencySeeder::class);

        $this->assertSame(5, DB::table('currencies')->count());
        $this->assertSame(1, DB::table('currencies')->where('is_base', true)->count());

        $duplicates = DB::table('currencies')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $rateDuplicates = DB::table('exchange_rates')
            ->select('base_currency_id', 'quote_currency_id', 'source', 'valid_from')
            ->groupBy('base_currency_id', 'quote_currency_id', 'source', 'valid_from')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $duplicates);
        $this->assertSame(0, $rateDuplicates);
    }
}
