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
        'paid_at',
        'failed_at',
        'expired_at',
        'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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

