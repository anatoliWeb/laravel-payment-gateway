<?php

namespace App\Http\Resources\Payments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_source' => $this->metadata['payment_source'] ?? null,
            'payment_method' => $this->payment_method,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'invoice_id' => $this->invoice_id,
            'wallet_transaction_id' => $this->metadata['wallet_transaction_id'] ?? null,
            'payment_method_summary' => $this->when(
                isset($this->metadata['payment_method_id']),
                fn () => [
                    'id' => $this->metadata['payment_method_id'] ?? null,
                    'uuid' => $this->metadata['payment_method_uuid'] ?? null,
                    'type' => $this->metadata['payment_method_type'] ?? null,
                ],
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
