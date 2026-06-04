<?php

namespace App\Services\Payments\Providers\Simulator;

use App\Services\Payments\Providers\Contracts\PaymentProviderInterface;
use App\Services\Payments\Providers\Contracts\ProviderWebhookVerifierInterface;
use App\Services\Payments\Providers\DTO\ProviderCapabilitiesData;
use App\Services\Payments\Providers\DTO\ProviderChargeData;
use App\Services\Payments\Providers\DTO\ProviderPaymentResponseData;
use App\Services\Payments\Providers\DTO\ProviderRefundData;
use App\Services\Payments\Providers\DTO\ProviderRefundResponseData;
use App\Services\Payments\Providers\DTO\ProviderStatusData;
use App\Services\Payments\Providers\DTO\ProviderWebhookData;
use App\Services\Payments\Providers\DTO\ProviderWebhookResultData;
use Illuminate\Support\Str;

class SimulatorPaymentProvider implements PaymentProviderInterface, ProviderWebhookVerifierInterface
{
    public const WEBHOOK_TEST_SIGNATURE = 'simulator-test-signature';

    public function charge(ProviderChargeData $data): ProviderPaymentResponseData
    {
        if ($data->amount <= 0) {
            return new ProviderPaymentResponseData(
                successful: false,
                status: 'failed',
                provider: 'simulator',
                errorCode: 'provider_charge_failed',
                errorMessage: 'Charge amount must be positive.',
            );
        }

        [$provider, $status, $prefix] = match ($data->paymentMethodType) {
            'fake_card' => ['simulator', 'processing', 'sim_'],
            'fake_manual_invoice' => ['manual', 'pending', 'manual_'],
            'fake_wallet' => ['internal_wallet', 'pending', 'wallet_'],
            default => ['simulator', 'failed', 'sim_failed_'],
        };

        if ($status === 'failed') {
            return new ProviderPaymentResponseData(
                successful: false,
                status: 'failed',
                provider: $provider,
                errorCode: 'provider_unsupported_operation',
                errorMessage: 'Payment method type is not supported by the simulator provider.',
            );
        }

        return new ProviderPaymentResponseData(
            successful: true,
            status: $status,
            provider: $provider,
            providerReference: $prefix.Str::lower(Str::random(16)),
            amount: $data->amount,
            currency: strtoupper($data->currency),
            rawResponse: [
                'simulator_safe' => true,
                'idempotency_forwarded' => $data->idempotencyKey !== null,
            ],
        );
    }

    public function refund(ProviderRefundData $data): ProviderRefundResponseData
    {
        return new ProviderRefundResponseData(
            successful: true,
            providerReference: $data->providerReference,
            refundReference: 'sim_ref_'.Str::lower(Str::random(16)),
            status: 'refunded',
        );
    }

    public function getStatus(string $providerReference): ProviderStatusData
    {
        return new ProviderStatusData(
            providerReference: $providerReference,
            status: str_starts_with($providerReference, 'manual_') ? 'pending' : 'processing',
            rawStatus: 'simulator_stable_status',
            metadata: ['simulator_safe' => true],
        );
    }

    public function verifyWebhook(ProviderWebhookData $data): ProviderWebhookResultData
    {
        $signature = $this->header($data->headers, 'x-simulator-signature');
        $payload = is_array($data->payload) ? $this->sanitize($data->payload) : [];

        if (! hash_equals(self::WEBHOOK_TEST_SIGNATURE, $signature)) {
            return new ProviderWebhookResultData(
                valid: false,
                payload: $payload,
                errorCode: 'provider_webhook_signature_invalid',
                errorMessage: 'Simulator webhook signature is invalid.',
            );
        }

        return new ProviderWebhookResultData(
            valid: true,
            eventType: isset($payload['event_type']) ? (string) $payload['event_type'] : null,
            providerReference: isset($payload['provider_reference']) ? (string) $payload['provider_reference'] : null,
            eventId: isset($payload['event_id']) ? (string) $payload['event_id'] : null,
            payload: $payload,
        );
    }

    public function capabilities(): ProviderCapabilitiesData
    {
        return new ProviderCapabilitiesData(
            supportsCharge: true,
            supportsRefund: true,
            supportsStatusLookup: true,
            supportsWebhookVerification: true,
            supportsManualInvoice: true,
            supportsRedirect: false,
            supportsTokenizedCard: false,
        );
    }

    private function header(array $headers, string $expected): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $expected) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        return '';
    }

    private function sanitize(array $payload): array
    {
        $forbidden = ['card_number', 'pan', 'cvv', 'cvc', 'security_code', 'token', 'secret', 'password', 'private_key'];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $forbidden, true)) {
                unset($payload[$key]);
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->sanitize($value);
            }
        }

        return $payload;
    }
}
