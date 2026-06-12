<?php

namespace App\Http\Resources\Billing\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProviderAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'company_id' => $this->company_id,
            'seller_id' => $this->seller_id,
            'provider' => $this->provider,
            'display_name' => $this->display_name,
            'status' => $this->status,
            'mode' => $this->mode,
            'config_source' => $this->config_source,
            'public_config' => $this->public_config ?? [],
            'capabilities' => $this->capabilities ?? [],
            'masked_credentials' => $this->getMaskedCredentials(),
            'last_verified_at' => $this->last_verified_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
