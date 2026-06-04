<?php

namespace App\Services\Payments\Providers\DTO;

final readonly class ProviderRefundResponseData
{
    public function __construct(
        public bool $successful,
        public ?string $providerReference,
        public ?string $refundReference,
        public string $status,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {
    }
}
