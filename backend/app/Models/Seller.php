<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Seller or merchant billing ownership scope.
 */
class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'owner_user_id',
        'name',
        'slug',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $seller): void {
            $seller->uuid ??= (string) Str::uuid();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'seller_customers')
            ->withPivot(['status', 'metadata'])
            ->withTimestamps();
    }

    public function customerLinks(): HasMany
    {
        return $this->hasMany(SellerCustomer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function providerAccounts(): HasMany
    {
        return $this->hasMany(PaymentProviderAccount::class);
    }
}
