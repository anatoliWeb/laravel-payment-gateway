<?php

namespace Tests\Feature\Billing;

use App\Services\Payments\Providers\PaymentProviderFactory;
use App\Services\Payments\Providers\Simulator\SimulatorPaymentProvider;
use RuntimeException;
use Tests\TestCase;

class PaymentProviderFactoryTest extends TestCase
{
    public function test_it_resolves_simulator_compatible_providers(): void
    {
        $factory = app(PaymentProviderFactory::class);

        $this->assertInstanceOf(SimulatorPaymentProvider::class, $factory->make('simulator'));
        $this->assertInstanceOf(SimulatorPaymentProvider::class, $factory->make('manual'));
        $this->assertInstanceOf(SimulatorPaymentProvider::class, $factory->make('internal_wallet'));
    }

    public function test_it_rejects_unknown_provider(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provider_not_configured');

        app(PaymentProviderFactory::class)->make('unknown-provider');
    }

    public function test_external_providers_are_disabled_in_demo_mode(): void
    {
        config()->set('billing.providers.external_enabled', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provider_disabled');

        app(PaymentProviderFactory::class)->make('stripe');
    }
}
