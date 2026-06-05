<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Represents an invoice lifecycle record that can later be linked to a payment attempt.
 */
class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_VOID = 'void';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    public const FINAL_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_VOID,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'uuid',
        'number',
        'user_id',
        'payer_user_id',
        'company_id',
        'seller_id',
        'subscription_id',
        'payment_id',
        'status',
        'currency',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'due_amount',
        'issued_at',
        'due_at',
        'paid_at',
        'voided_at',
        'overdue_at',
        'description',
        'metadata',
        'ownership_metadata',
    ];

    protected $casts = [
        'subtotal_amount' => 'integer',
        'discount_amount' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'due_amount' => 'integer',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
        'overdue_at' => 'datetime',
        'metadata' => 'array',
        'ownership_metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            $invoice->uuid ??= (string) Str::uuid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
