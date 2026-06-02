<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a billing catalog plan used for subscriptions and feature access.
 */
class Plan extends Model
{
    use HasFactory;

    protected $table = 'plans';

    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'description',
        'type',
        'price_amount',
        'currency',
        'billing_interval',
        'trial_days',
        'is_active',
        'is_public',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'price_amount' => 'integer',
        'trial_days' => 'integer',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    // WHY: Feature limits are stored as rows so chat and future dialer modules
    // can share one billing engine without schema duplication.
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    // WHY: Plan slugs are stable business identifiers used by seeders, tests,
    // and API contracts.
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
