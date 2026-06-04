<?php

namespace App\Services\Payments\Providers\DTO;

final readonly class ProviderErrorData
{
    public function __construct(
        public string $code,
        public string $message,
        public bool $retryable = false,
        public array $metadata = [],
    ) {
    }
}
