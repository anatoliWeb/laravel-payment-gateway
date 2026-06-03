<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Current wallet balance for one currency.
 */
class WalletBalance extends Model
{
    use HasFactory;

    protected $table = 'wallet_balances';

    protected $fillable = [
        'wallet_id',
        'currency_id',
        'available_amount',
        'held_amount',
        'metadata',
    ];

    protected $casts = [
        'available_amount' => 'integer',
        'held_amount' => 'integer',
        'metadata' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // WHY: Ledger entries reference balance snapshots so balance changes remain auditable.
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
