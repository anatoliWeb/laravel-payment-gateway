<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_exchange_rate_relations_casts_and_validity_fields_work(): void
    {
        $usd = Currency::factory()->usd()->base()->create();
        $uah = Currency::factory()->uah()->create();

        $rate = ExchangeRate::factory()->simulated()->create([
            'base_currency_id' => $usd->id,
            'quote_currency_id' => $uah->id,
            'rate' => '40.12345678',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'is_active' => true,
        ]);

        $rate->refresh();

        $this->assertSame('USD', $rate->baseCurrency->code);
        $this->assertSame('UAH', $rate->quoteCurrency->code);
        $this->assertSame('40.12345678', $rate->rate);
        $this->assertSame('simulated', $rate->source);
        $this->assertTrue($rate->is_active);
        $this->assertNotNull($rate->valid_from);
        $this->assertNotNull($rate->valid_until);
        $this->assertSame(1, ExchangeRate::query()->active()->count());
    }
}
