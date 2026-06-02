<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents one feature flag or usage limit attached to a billing plan.
 */
class PlanFeature extends Model
{
    use HasFactory;

    protected $table = 'plan_features';

    protected $fillable = [
        'plan_id',
        'feature_key',
        'value',
        'value_type',
        'period',
        'reset_policy',
        'is_enabled',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'metadata' => 'array',
    ];

    // WHY: Feature keys stay module-agnostic so billing is not coupled to
    // chat-only rules and can be reused by future domains.
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
