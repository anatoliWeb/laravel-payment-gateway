<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents one invoice line item with integer minor-unit amounts.
 */
class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'item_type',
        'description',
        'quantity',
        'unit_amount',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_amount' => 'integer',
        'subtotal_amount' => 'integer',
        'discount_amount' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'metadata' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
