<?php

namespace App\Http\Resources\Billing\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AdminIdempotencyKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'scope' => $this->scope,
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'status' => $this->status,
            'key_fingerprint' => $this->fingerprint((string) $this->key),
            'request_hash' => $this->request_hash,
            'response_status' => $this->response_status,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'locked_until' => $this->locked_until?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function fingerprint(string $key): string
    {
        return 'sha256:'.Str::substr(hash('sha256', $key), 0, 12);
    }
}
