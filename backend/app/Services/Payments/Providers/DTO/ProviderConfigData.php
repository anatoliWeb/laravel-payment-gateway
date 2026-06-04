<?php

namespace App\Services\Payments\Providers\DTO;

final readonly class ProviderConfigData
{
    public function __construct(
        public string $provider,
        public string $source,
        public bool $enabled,
        public string $mode = 'test',
        public array $credentials = [],
        public array $publicConfig = [],
        public ?int $providerAccountId = null,
        public ?string $errorCode = null,
    ) {
    }
}
