<?php

namespace App\Services\Payments\Providers\DTO;

final readonly class ProviderRefundData
{
    public function __construct(
        public string $providerReference,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $reason = null,
        public array $metadata = [],
        public array $providerConfig = [],
    ) {
    }
}
