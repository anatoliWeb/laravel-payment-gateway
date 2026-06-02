<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a manual billing, payment, or feature restriction for a user.
 */
class BillingRestriction extends Model
{
    use HasFactory;

    protected $table = 'billing_restrictions';

    protected $fillable = [
        'user_id',
        'type',
        'scope',
        'feature_key',
        'reason',
        'is_active',
        'starts_at',
        'ends_at',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    // WHY: Restrictions stay separate from plan features so manual/admin
    // blocks do not mutate shared commercial plan definitions.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
