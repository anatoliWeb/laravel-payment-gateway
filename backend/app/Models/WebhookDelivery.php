<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents one outgoing billing webhook delivery record.
 */
class WebhookDelivery extends Model
{
    use HasFactory;

    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'uuid',
        'payment_id',
        'subscription_id',
        'invoice_id',
        'event',
        'url',
        'status',
        'payload',
        'response_status',
        'response_body',
        'attempts',
        'max_attempts',
        'next_retry_at',
        'last_attempt_at',
        'delivered_at',
        'failed_at',
        'metadata',
    ];

    protected $casts = [
        'payload' => 'array',
        'metadata' => 'array',
        'response_status' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // WHY: HTTP callbacks belong to queue jobs and services; the model only
    // stores delivery state and persistence metadata.
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}

