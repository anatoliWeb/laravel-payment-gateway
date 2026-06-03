<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only wallet ledger entry for balance movements.
 */
class WalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'wallet_transactions';

    protected $fillable = [
        'uuid',
        'wallet_id',
        'wallet_balance_id',
        'currency_id',
        'payment_id',
        'subscription_id',
        'type',
        'direction',
        'amount',
        'balance_available_before',
        'balance_available_after',
        'balance_held_before',
        'balance_held_after',
        'idempotency_key',
        'reference_type',
        'reference_id',
        'reason',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_available_before' => 'integer',
        'balance_available_after' => 'integer',
        'balance_held_before' => 'integer',
        'balance_held_after' => 'integer',
        'metadata' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function walletBalance(): BelongsTo
    {
        return $this->belongsTo(WalletBalance::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    // WHY: Future billing operations can correlate wallet entries without
    // adding schema-specific columns for every new domain object.
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
