<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents period-based usage counters used by feature access checks.
 */
class FeatureUsage extends Model
{
    use HasFactory;

    protected $table = 'feature_usages';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'plan_id',
        'feature_key',
        'period',
        'period_start',
        'period_end',
        'used',
        'limit_value',
        'reset_at',
        'metadata',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'used' => 'integer',
        'limit_value' => 'integer',
        'reset_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // WHY: Usage rows are period-bound to support daily, monthly, and
    // billing-cycle limits without changing the subscription record itself.
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
