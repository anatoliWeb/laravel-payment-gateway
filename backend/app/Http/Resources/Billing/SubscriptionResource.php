<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes subscription lifecycle state for billing API responses.
 */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'plan_slug' => $this->plan?->slug,
            'status' => $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'current_period_start' => $this->current_period_start?->toISOString(),
            'current_period_end' => $this->current_period_end?->toISOString(),
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
            'ended_at' => $this->ended_at?->toISOString(),
            'metadata' => $this->safeMetadata((array) $this->metadata),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function safeMetadata(array $metadata): array
    {
        unset($metadata['creation_idempotency_hash']);

        return $metadata;
    }
}
