<?php

namespace App\Http\Resources\Billing\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'payer_user_id' => $this->payer_user_id,
            'company_id' => $this->company_id,
            'seller_id' => $this->seller_id,
            'subscription_id' => $this->subscription_id,
            'invoice_id' => $this->invoice_id,
            'parent_payment_id' => $this->parent_payment_id,
            'provider_account_id' => $this->provider_account_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'description' => $this->description,
            'failure_reason' => $this->failure_reason,
            'callback_url' => $this->callback_url,
            'payment_source' => $this->metadata['payment_source'] ?? null,
            'wallet_transaction_id' => $this->metadata['wallet_transaction_id'] ?? null,
            'payment_method_summary' => $this->when(
                isset($this->metadata['payment_method_id']),
                fn () => [
                    'id' => $this->metadata['payment_method_id'] ?? null,
                    'uuid' => $this->metadata['payment_method_uuid'] ?? null,
                    'type' => $this->metadata['payment_method_type'] ?? null,
                ],
            ),
            'metadata' => $this->safeMetadata((array) $this->metadata),
            'ownership_metadata' => $this->ownership_metadata,
            'paid_at' => $this->paid_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'expired_at' => $this->expired_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'transactions_count' => $this->whenCounted('transactions'),
            'webhook_deliveries_count' => $this->whenCounted('webhookDeliveries'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function safeMetadata(array $metadata): array
    {
        unset(
            $metadata['idempotency_key'],
            $metadata['raw_idempotency_key'],
            $metadata['provider_config'],
            $metadata['credentials'],
            $metadata['secret'],
            $metadata['password'],
            $metadata['private_key'],
        );

        return $metadata;
    }
}
