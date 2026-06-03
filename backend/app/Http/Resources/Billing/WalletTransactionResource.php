<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'direction' => $this->direction,
            'amount' => $this->amount,
            'currency' => $this->currency?->code,
            'status' => $this->status,
            'reason' => $this->reason,
            'payment_uuid' => $this->payment?->uuid,
            'balance_available_before' => $this->balance_available_before,
            'balance_available_after' => $this->balance_available_after,
            'balance_held_before' => $this->balance_held_before,
            'balance_held_after' => $this->balance_held_after,
            'metadata' => $this->safeMetadata((array) $this->metadata),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function safeMetadata(array $metadata): array
    {
        return array_intersect_key($metadata, array_flip([
            'source',
            'reason',
            'auto_top_up',
            'manual_wallet_top_up',
        ]));
    }
}
