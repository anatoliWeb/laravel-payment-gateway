<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User billing strategy and explicit consent settings.
 */
class UserPaymentPreference extends Model
{
    use HasFactory;

    protected $table = 'user_payment_preferences';

    protected $fillable = [
        'user_id',
        'default_payment_method_id',
        'strategy',
        'auto_charge_enabled',
        'auto_top_up_enabled',
        'auto_top_up_threshold_amount',
        'auto_top_up_amount',
        'auto_top_up_currency_id',
        'max_auto_top_up_per_day',
        'max_auto_top_up_per_month',
        'auto_charge_consent_at',
        'auto_top_up_consent_at',
        'metadata',
    ];

    protected $casts = [
        'auto_charge_enabled' => 'boolean',
        'auto_top_up_enabled' => 'boolean',
        'auto_top_up_threshold_amount' => 'integer',
        'auto_top_up_amount' => 'integer',
        'max_auto_top_up_per_day' => 'integer',
        'max_auto_top_up_per_month' => 'integer',
        'auto_charge_consent_at' => 'datetime',
        'auto_top_up_consent_at' => 'datetime',
        'metadata' => 'array',
    ];

    // WHY: Preferences store strategy and consent separately from payment
    // methods so saved instruments do not imply automatic charge permission.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    public function autoTopUpCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'auto_top_up_currency_id');
    }
}
