<?php

namespace App\Services\Payments\Providers\DTO;

final readonly class ProviderCapabilitiesData
{
    public function __construct(
        public bool $supportsCharge,
        public bool $supportsRefund,
        public bool $supportsStatusLookup,
        public bool $supportsWebhookVerification,
        public bool $supportsManualInvoice,
        public bool $supportsRedirect,
        public bool $supportsTokenizedCard,
    ) {
    }
}
