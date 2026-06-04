<?php

namespace App\Services\Payments\Providers\DTO;

final readonly class ProviderWebhookData
{
    public function __construct(
        public string $provider,
        public array $headers,
        public array|string $payload,
        public ?string $rawBody = null,
        public ?int $providerAccountId = null,
    ) {
    }
}
