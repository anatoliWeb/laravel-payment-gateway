<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Billing\CurrencyConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_currency_conversion_returns_same_minor_amount(): void
    {
        Currency::factory()->usd()->base()->create();

        $result = app(CurrencyConversionService::class)->convertMinorAmount(12345, 'USD', 'USD');

        $this->assertSame(12345, $result);
    }

    public function test_converts_minor_amount_using_active_manual_rate(): void
    {
        [$usd, $eur] = $this->createCurrencyPair('EUR', 2);
        $this->createRate($usd, $eur, '0.92000000');

        $result = app(CurrencyConversionService::class)->convertMinorAmount(1000, 'USD', 'EUR');

        $this->assertSame(920, $result);
    }

    public function test_missing_rate_returns_null(): void
    {
        Currency::factory()->usd()->base()->create();
        Currency::factory()->eur()->create();

        $result = app(CurrencyConversionService::class)->convertMinorAmount(1000, 'EUR', 'USD');

        $this->assertNull($result);
    }

    public function test_rounding_is_deterministic_for_target_precision(): void
    {
        [$usd, $eur] = $this->createCurrencyPair('EUR', 2);
        $this->createRate($usd, $eur, '0.33500000');

        $result = app(CurrencyConversionService::class)->convertMinorAmount(100, 'USD', 'EUR');

        $this->assertSame(34, $result);
    }

    /**
     * @return array{Currency, Currency}
     */
    private function createCurrencyPair(string $quoteCode, int $quotePrecision): array
    {
        $usd = Currency::factory()->usd()->base()->create();
        $quote = Currency::factory()->create([
            'code' => $quoteCode,
            'name' => $quoteCode.' Currency',
            'decimal_precision' => $quotePrecision,
            'is_active' => true,
            'is_base' => false,
        ]);

        return [$usd, $quote];
    }

    private function createRate(Currency $base, Currency $quote, string $rate): ExchangeRate
    {
        return ExchangeRate::factory()->active()->create([
            'base_currency_id' => $base->id,
            'quote_currency_id' => $quote->id,
            'rate' => $rate,
            'source' => 'manual',
        ]);
    }
}
