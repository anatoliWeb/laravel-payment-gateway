<?php

namespace App\Services\Payments\Providers\Contracts;

use App\Services\Payments\Providers\DTO\ProviderWebhookData;
use App\Services\Payments\Providers\DTO\ProviderWebhookResultData;

interface ProviderWebhookVerifierInterface
{
    public function verifyWebhook(ProviderWebhookData $data): ProviderWebhookResultData;
}
