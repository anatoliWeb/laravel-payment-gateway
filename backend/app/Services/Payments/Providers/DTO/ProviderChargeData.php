<?php

namespace App\Services\Payments\Providers\DTO;

final readonly class ProviderChargeData
{
    public function __construct(
        public int $amount,
        public string $currency,
        public ?string $paymentMethodType = null,
        public ?string $paymentMethodReference = null,
        public ?string $customerReference = null,
        public ?string $description = null,
        public array $metadata = [],
        public ?string $idempotencyKey = null,
        public ?string $callbackUrl = null,
        public array $providerConfig = [],
    ) {
    }
}
