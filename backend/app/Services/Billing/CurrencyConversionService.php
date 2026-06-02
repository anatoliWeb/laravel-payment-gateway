<?php

namespace App\Services\Billing;

class CurrencyConversionService
{
    private const RATE_SCALE = 8;

    public function __construct(
        private readonly CurrencyService $currencyService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {
    }

    public function convertMinorAmount(int $amount, string $fromCode, string $toCode): ?int
    {
        $fromCode = strtoupper($fromCode);
        $toCode = strtoupper($toCode);

        if ($fromCode === $toCode) {
            return $amount;
        }

        $from = $this->currencyService->findByCode($fromCode);
        $to = $this->currencyService->findByCode($toCode);
        if (! $from || ! $to || ! $from->is_active || ! $to->is_active) {
            return null;
        }

        $rate = $this->exchangeRateService->getActiveRate($fromCode, $toCode);
        if (! $rate) {
            return null;
        }

        // WHY: Keep money conversion deterministic without float arithmetic.
        // rate uses decimal(20, 8), so we scale it to an integer and round
        // half-up after applying source/target currency precision.
        $rateUnits = $this->decimalToScaledInteger((string) $rate->rate, self::RATE_SCALE);
        $numerator = $amount * $rateUnits * (10 ** $to->decimal_precision);
        $denominator = (10 ** $from->decimal_precision) * (10 ** self::RATE_SCALE);

        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function decimalToScaledInteger(string $value, int $scale): int
    {
        $normalized = trim($value);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, $scale), $scale, '0');

        return ((int) $whole * (10 ** $scale)) + (int) $fraction;
    }
}
