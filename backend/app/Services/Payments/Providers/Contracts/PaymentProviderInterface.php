<?php

namespace App\Services\Payments\Providers\Contracts;

use App\Services\Payments\Providers\DTO\ProviderCapabilitiesData;
use App\Services\Payments\Providers\DTO\ProviderChargeData;
use App\Services\Payments\Providers\DTO\ProviderPaymentResponseData;
use App\Services\Payments\Providers\DTO\ProviderRefundData;
use App\Services\Payments\Providers\DTO\ProviderRefundResponseData;
use App\Services\Payments\Providers\DTO\ProviderStatusData;
use App\Services\Payments\Providers\DTO\ProviderWebhookData;
use App\Services\Payments\Providers\DTO\ProviderWebhookResultData;

interface PaymentProviderInterface
{
    public function charge(ProviderChargeData $data): ProviderPaymentResponseData;

    public function refund(ProviderRefundData $data): ProviderRefundResponseData;

    public function getStatus(string $providerReference): ProviderStatusData;

    public function verifyWebhook(ProviderWebhookData $data): ProviderWebhookResultData;

    public function capabilities(): ProviderCapabilitiesData;
}
