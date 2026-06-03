<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'balances' => $this->relationLoaded('balances')
                ? WalletBalanceResource::collection($this->balances)->resolve()
                : [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
