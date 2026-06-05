<?php

namespace App\Http\Resources\Payments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'payment_id' => $this->payment_id,
            'event_type' => $this->event,
            'status' => $this->status,
            'attempts' => (int) $this->attempts,
            'max_attempts' => (int) $this->max_attempts,
            'next_attempt_at' => $this->next_retry_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'last_error' => $this->response_body !== null ? mb_substr((string) $this->response_body, 0, 300) : null,
            'status_code' => $this->response_status,
            'callback_host' => parse_url((string) $this->url, PHP_URL_HOST),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
