<?php

namespace App\Services\Billing;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

class CurrencyService
{
    /**
     * @return Collection<int, Currency>
     */
    public function getActiveCurrencies(): Collection
    {
        return Currency::query()
            ->active()
            ->orderBy('code')
            ->get();
    }

    public function getBaseCurrency(): ?Currency
    {
        return Currency::query()
            ->base()
            ->active()
            ->orderBy('id')
            ->first();
    }

    public function findByCode(string $code): ?Currency
    {
        return Currency::query()
            ->where('code', strtoupper($code))
            ->first();
    }
}
