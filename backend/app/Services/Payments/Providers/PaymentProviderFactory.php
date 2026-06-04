<?php

namespace App\Services\Payments\Providers;

use App\Services\Payments\Providers\Contracts\PaymentProviderInterface;
use App\Services\Payments\Providers\Simulator\SimulatorPaymentProvider;
use RuntimeException;

class PaymentProviderFactory
{
    public function make(string $provider): PaymentProviderInterface
    {
        $provider = strtolower($provider);

        if (in_array($provider, ['simulator', 'manual', 'internal_wallet'], true)) {
            return app(SimulatorPaymentProvider::class);
        }

        if (in_array($provider, ['stripe', 'paypal', 'liqpay', 'wayforpay', 'monobank', 'fondy'], true)) {
            throw new RuntimeException(
                config('billing.providers.external_enabled', false)
                    ? 'provider_not_configured'
                    : 'provider_disabled',
            );
        }

        throw new RuntimeException('provider_not_configured');
    }
}
