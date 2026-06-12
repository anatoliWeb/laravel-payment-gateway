<?php

namespace App\Http\Resources\Billing\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPaymentTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'type' => $this->type,
            'status_from' => $this->status_from,
            'status_to' => $this->status_to,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->message,
            'payload' => $this->safePayload((array) $this->payload),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function safePayload(array $payload): array
    {
        unset(
            $payload['idempotency_key'],
            $payload['raw_idempotency_key'],
            $payload['card_number'],
            $payload['cvv'],
            $payload['cvc'],
            $payload['pan'],
            $payload['security_code'],
            $payload['secret'],
            $payload['password'],
            $payload['private_key'],
        );

        return $payload;
    }
}
