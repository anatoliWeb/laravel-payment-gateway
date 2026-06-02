<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a user's billing subscription lifecycle and active plan context.
 */
class Subscription extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';

    protected $fillable = [
        'uuid',
        'user_id',
        'plan_id',
        'status',
        'started_at',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'cancelled_at',
        'cancel_at_period_end',
        'ended_at',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // WHY: Payment relations are intentionally added later in Phase 9 once the
    // payment-side models exist and can keep the boundary explicit.
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // WHY: Subscriptions keep payment history through a relation, while
    // activation and renewal decisions stay in the service layer.
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
