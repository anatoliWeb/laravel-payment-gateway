<?php

namespace App\Services\Payments\Providers\DTO;

final readonly class ProviderWebhookResultData
{
    public function __construct(
        public bool $valid,
        public ?string $eventType = null,
        public ?string $providerReference = null,
        public ?string $eventId = null,
        public array $payload = [],
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {
    }
}
