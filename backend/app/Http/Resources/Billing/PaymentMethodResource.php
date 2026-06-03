<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'provider' => $this->provider,
            'status' => $this->status,
            'display_name' => $this->display_name,
            'brand' => $this->brand,
            'last4' => $this->last4,
            'exp_month' => $this->exp_month,
            'exp_year' => $this->exp_year,
            'is_default' => $this->is_default,
            'consent_given_at' => $this->consent_given_at?->toISOString(),
            'metadata' => $this->safeMetadata((array) $this->metadata),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function safeMetadata(array $metadata): array
    {
        return array_intersect_key($metadata, array_flip([
            'source',
            'simulator_safe',
        ]));
    }
}
