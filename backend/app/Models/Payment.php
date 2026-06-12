<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a persisted payment attempt within the billing module.
 */
class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'uuid',
        'user_id',
        'payer_user_id',
        'company_id',
        'seller_id',
        'provider_account_id',
        'subscription_id',
        'invoice_id',
        'parent_payment_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'provider',
        'provider_reference',
        'description',
        'failure_reason',
        'callback_url',
        'metadata',
        'ownership_metadata',
        'paid_at',
        'failed_at',
        'expired_at',
        'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'ownership_metadata' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Resolve payment route bindings by id or UUID.
     *
     * WHY:
     * The checkout UI receives UUIDs while older admin tooling still uses
     * numeric ids. Accepting both keeps the API backward-compatible and lets
     * local/demo pages use the public UUID without a second endpoint shape.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $query = static::query();

        if ($field !== null && $field !== $this->getRouteKeyName()) {
            return $query->where($field, $value)->first();
        }

        return $query
            ->whereKey($value)
            ->orWhere('uuid', $value)
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Explicit payer relation added without removing legacy user ownership.
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentProviderAccount::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // WHY: Retry attempts are modeled as new payment rows so the original
    // attempt stays immutable and audit-friendly.
    public function parentPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_payment_id');
    }

    public function retryPayments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_payment_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    // WHY: Payment state transitions belong to future services so the model
    // remains a persistence layer instead of a state machine.
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
