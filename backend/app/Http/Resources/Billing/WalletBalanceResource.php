<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency' => [
                'code' => $this->currency?->code,
                'name' => $this->currency?->name,
                'symbol' => $this->currency?->symbol,
                'decimal_precision' => $this->currency?->decimal_precision,
            ],
            'available_amount' => $this->available_amount,
            'held_amount' => $this->held_amount,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
