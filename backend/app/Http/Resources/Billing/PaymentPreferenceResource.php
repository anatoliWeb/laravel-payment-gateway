<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'strategy' => $this->strategy,
            'default_payment_method' => $this->when(
                $this->relationLoaded('defaultPaymentMethod') && $this->defaultPaymentMethod,
                fn () => (new PaymentMethodResource($this->defaultPaymentMethod))->resolve(),
            ),
            'auto_charge_enabled' => $this->auto_charge_enabled,
            'auto_charge_consent_at' => $this->auto_charge_consent_at?->toISOString(),
            'auto_top_up_enabled' => $this->auto_top_up_enabled,
            'auto_top_up_consent_at' => $this->auto_top_up_consent_at?->toISOString(),
            'auto_top_up_threshold_amount' => $this->auto_top_up_threshold_amount,
            'auto_top_up_amount' => $this->auto_top_up_amount,
            'auto_top_up_currency' => $this->when(
                $this->relationLoaded('autoTopUpCurrency') && $this->autoTopUpCurrency,
                fn () => [
                    'code' => $this->autoTopUpCurrency->code,
                    'name' => $this->autoTopUpCurrency->name,
                    'symbol' => $this->autoTopUpCurrency->symbol,
                ],
            ),
            'max_auto_top_up_per_day' => $this->max_auto_top_up_per_day,
            'max_auto_top_up_per_month' => $this->max_auto_top_up_per_month,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
