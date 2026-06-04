<?php

namespace App\Services\Payments\Providers\DTO;

final readonly class ProviderStatusData
{
    public function __construct(
        public string $providerReference,
        public string $status,
        public ?string $rawStatus = null,
        public array $metadata = [],
    ) {
    }
}
