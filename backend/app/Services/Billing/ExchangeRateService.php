<?php

namespace App\Services\Billing;

use App\Models\ExchangeRate;

class ExchangeRateService
{
    public function getActiveRate(string $baseCode, string $quoteCode): ?ExchangeRate
    {
        $baseCode = strtoupper($baseCode);
        $quoteCode = strtoupper($quoteCode);

        return ExchangeRate::query()
            ->with(['baseCurrency', 'quoteCurrency'])
            ->active()
            ->whereHas('baseCurrency', fn ($query) => $query->where('code', $baseCode)->where('is_active', true))
            ->whereHas('quoteCurrency', fn ($query) => $query->where('code', $quoteCode)->where('is_active', true))
            ->where('valid_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            })
            ->latest('valid_from')
            ->latest('id')
            ->first();
    }
}
