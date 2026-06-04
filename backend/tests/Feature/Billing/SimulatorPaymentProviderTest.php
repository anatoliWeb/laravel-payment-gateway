<?php

namespace Tests\Feature\Billing;

use App\Services\Payments\Providers\DTO\ProviderChargeData;
use App\Services\Payments\Providers\DTO\ProviderRefundData;
use App\Services\Payments\Providers\DTO\ProviderWebhookData;
use App\Services\Payments\Providers\Simulator\SimulatorPaymentProvider;
use Tests\TestCase;

class SimulatorPaymentProviderTest extends TestCase
{
    public function test_fake_card_charge_returns_safe_processing_response(): void
    {
        $response = app(SimulatorPaymentProvider::class)->charge(new ProviderChargeData(
            amount: 2500,
            currency: 'USD',
            paymentMethodType: 'fake_card',
            idempotencyKey: 'simulator-charge-1',
            metadata: ['token' => 'must-not-return'],
        ));

        $this->assertTrue($response->successful);
        $this->assertSame('processing', $response->status);
        $this->assertSame('simulator', $response->provider);
        $this->assertStringStartsWith('sim_', $response->providerReference);
        $this->assertSame(['simulator_safe' => true, 'idempotency_forwarded' => true], $response->rawResponse);
    }

    public function test_manual_invoice_charge_returns_pending_manual_response(): void
    {
        $response = app(SimulatorPaymentProvider::class)->charge(new ProviderChargeData(
            amount: 2500,
            currency: 'USD',
            paymentMethodType: 'fake_manual_invoice',
        ));

        $this->assertTrue($response->successful);
        $this->assertSame('pending', $response->status);
        $this->assertSame('manual', $response->provider);
        $this->assertStringStartsWith('manual_', $response->providerReference);
    }

    public function test_status_and_refund_are_stable_fake_results(): void
    {
        $provider = app(SimulatorPaymentProvider::class);
        $status = $provider->getStatus('sim_reference');
        $refund = $provider->refund(new ProviderRefundData('sim_reference', 500, 'USD'));

        $this->assertSame('processing', $status->status);
        $this->assertTrue($refund->successful);
        $this->assertSame('refunded', $refund->status);
        $this->assertStringStartsWith('sim_ref_', $refund->refundReference);
    }

    public function test_webhook_verification_is_predictable_and_sanitized(): void
    {
        $provider = app(SimulatorPaymentProvider::class);
        $valid = $provider->verifyWebhook(new ProviderWebhookData(
            provider: 'simulator',
            headers: ['X-Simulator-Signature' => SimulatorPaymentProvider::WEBHOOK_TEST_SIGNATURE],
            payload: [
                'event_id' => 'evt_1',
                'event_type' => 'payment.updated',
                'provider_reference' => 'sim_1',
                'secret' => 'must-not-return',
            ],
        ));
        $invalid = $provider->verifyWebhook(new ProviderWebhookData(
            provider: 'simulator',
            headers: ['X-Simulator-Signature' => 'invalid'],
            payload: [],
        ));

        $this->assertTrue($valid->valid);
        $this->assertArrayNotHasKey('secret', $valid->payload);
        $this->assertFalse($invalid->valid);
        $this->assertSame('provider_webhook_signature_invalid', $invalid->errorCode);
    }

    public function test_capabilities_are_explicit(): void
    {
        $capabilities = app(SimulatorPaymentProvider::class)->capabilities();

        $this->assertTrue($capabilities->supportsCharge);
        $this->assertTrue($capabilities->supportsRefund);
        $this->assertTrue($capabilities->supportsStatusLookup);
        $this->assertTrue($capabilities->supportsWebhookVerification);
        $this->assertFalse($capabilities->supportsRedirect);
    }
}
