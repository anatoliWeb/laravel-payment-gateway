<?php

namespace App\Http\Resources\Billing\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFeatureOverrideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'subscription_id' => $this->subscription_id,
            'feature_key' => $this->feature_key,
            'value' => $this->value,
            'value_type' => $this->value_type,
            'period' => $this->period,
            'reset_policy' => $this->reset_policy,
            'is_enabled' => $this->is_enabled,
            'priority' => $this->priority,
            'reason' => $this->reason,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'created_by' => $this->created_by,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
