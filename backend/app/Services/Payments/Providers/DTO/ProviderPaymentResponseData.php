<?php

namespace App\Services\Payments\Providers\DTO;

final readonly class ProviderPaymentResponseData
{
    public function __construct(
        public bool $successful,
        public string $status,
        public string $provider,
        public ?string $providerReference = null,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $rawResponse = [],
    ) {
    }
}
