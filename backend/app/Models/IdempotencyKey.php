<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents stored idempotency request metadata for payment writes.
 */
class IdempotencyKey extends Model
{
    use HasFactory;

    protected $table = 'idempotency_keys';

    protected $fillable = [
        'key',
        'user_id',
        'scope',
        'method',
        'endpoint',
        'request_hash',
        'response_body',
        'response_status',
        'related_type',
        'related_id',
        'status',
        'locked_until',
        'expires_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'response_status' => 'integer',
        'locked_until' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // WHY: The model stores replay state, but request guarding and conflict
    // resolution belong to a dedicated idempotency service later on.
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
