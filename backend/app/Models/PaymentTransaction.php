<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents one append-only payment timeline record.
 */
class PaymentTransaction extends Model
{
    use HasFactory;

    protected $table = 'payment_transactions';

    protected $fillable = [
        'payment_id',
        'type',
        'status_from',
        'status_to',
        'amount',
        'currency',
        'message',
        'payload',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payload' => 'array',
    ];

    // WHY: Transaction rows are kept append-only so payment history can be
    // inspected without rewriting prior lifecycle events.
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

