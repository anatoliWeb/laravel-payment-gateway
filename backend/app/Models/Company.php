<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Top-level additive billing ownership scope.
 *
 * Company scope prepares reporting, provider, and webhook isolation while
 * existing user-scoped billing records remain valid.
 */
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
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
        static::creating(function (self $company): void {
            $company->uuid ??= (string) Str::uuid();
        });
    }

    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_users')
            ->withPivot(['role', 'status', 'metadata'])
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function providerAccounts(): HasMany
    {
        return $this->hasMany(PaymentProviderAccount::class);
    }
}
