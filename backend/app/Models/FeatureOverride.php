<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a manual feature access or limit override for one user/subscription.
 */
class FeatureOverride extends Model
{
    use HasFactory;

    protected $table = 'feature_overrides';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'feature_key',
        'value',
        'value_type',
        'period',
        'reset_policy',
        'is_enabled',
        'priority',
        'reason',
        'starts_at',
        'ends_at',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'priority' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // WHY: Overrides stay separate from plan_features so individual exceptions
    // do not corrupt shared plan limits used by the rest of the catalog.
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
