<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_casts_scopes_and_relations_work(): void
    {
        $usd = Currency::factory()->usd()->base()->create([
            'code' => 'usd',
            'decimal_precision' => 2,
            'metadata' => ['source' => 'test'],
        ]);
        $eur = Currency::factory()->eur()->create();
        Currency::factory()->pln()->inactive()->create();

        ExchangeRate::factory()->create([
            'base_currency_id' => $usd->id,
            'quote_currency_id' => $eur->id,
            'rate' => '0.92000000',
        ]);

        $usd->refresh();

        $this->assertSame('USD', $usd->code);
        $this->assertSame(2, $usd->decimal_precision);
        $this->assertTrue($usd->is_active);
        $this->assertTrue($usd->is_base);
        $this->assertSame(['source' => 'test'], $usd->metadata);
        $this->assertSame(['EUR', 'USD'], Currency::query()->active()->orderBy('code')->pluck('code')->all());
        $this->assertSame('USD', Currency::query()->base()->firstOrFail()->code);
        $this->assertSame(1, $usd->baseExchangeRates()->count());
        $this->assertSame(1, $eur->quoteExchangeRates()->count());
    }
}
