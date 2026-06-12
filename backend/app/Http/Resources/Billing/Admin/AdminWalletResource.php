<?php

namespace App\Http\Resources\Billing\Admin;

use App\Http\Resources\Billing\WalletBalanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminWalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'balances' => $this->relationLoaded('balances')
                ? WalletBalanceResource::collection($this->balances)->resolve()
                : [],
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
