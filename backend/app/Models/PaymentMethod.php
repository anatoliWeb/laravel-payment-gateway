<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Simulator-safe billing instrument, not a raw card vault record.
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'payment_methods';

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'provider',
        'status',
        'display_name',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'provider_reference',
        'is_default',
        'consent_given_at',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'exp_month' => 'integer',
        'exp_year' => 'integer',
        'consent_given_at' => 'datetime',
        'metadata' => 'array',
    ];

    // WHY: Payment methods are user-owned simulator records and must not
    // contain provider secrets, raw card numbers, or CVV values.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
