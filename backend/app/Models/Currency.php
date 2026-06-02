<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Currency catalog entity reused by billing, wallets, and payments.
 */
class Currency extends Model
{
    use HasFactory;

    protected $table = 'currencies';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_precision',
        'is_active',
        'is_base',
        'description',
        'metadata',
    ];

    protected $casts = [
        'decimal_precision' => 'integer',
        'is_active' => 'boolean',
        'is_base' => 'boolean',
        'metadata' => 'array',
    ];

    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value): string => strtoupper($value));
    }

    // WHY: Exchange rates are stored separately so wallets and payments can
    // reuse the same currency catalog without duplicating rate metadata.
    public function baseExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'base_currency_id');
    }

    public function quoteExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'quote_currency_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBase(Builder $query): Builder
    {
        return $query->where('is_base', true);
    }
}
