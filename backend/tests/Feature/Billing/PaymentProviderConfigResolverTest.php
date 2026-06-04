<?php

namespace Tests\Feature\Billing;

use App\Models\PaymentProviderAccount;
use App\Models\User;
use App\Services\Payments\Providers\PaymentProviderConfigResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentProviderConfigResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulator_default_resolves_without_real_secrets(): void
    {
        config()->set('billing.providers.platform.simulator.enabled', false);
        config()->set('billing.providers.simulator_default', true);

        $config = app(PaymentProviderConfigResolver::class)->resolve('simulator');

        $this->assertTrue($config->enabled);
        $this->assertSame('default', $config->source);
        $this->assertSame([], $config->credentials);
    }

    public function test_platform_env_config_can_be_resolved_safely(): void
    {
        config()->set('billing.providers.platform.stripe', [
            'enabled' => true,
            'mode' => 'test',
            'credentials' => ['secret_key' => 'fake_env_secret'],
            'public_config' => ['region' => 'test'],
        ]);

        $config = app(PaymentProviderConfigResolver::class)->resolve('stripe');

        $this->assertTrue($config->enabled);
        $this->assertSame('env', $config->source);
        $this->assertSame('fake_env_secret', $config->credentials['secret_key']);
    }

    public function test_database_account_has_priority_and_is_user_isolated(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = PaymentProviderAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'simulator',
            'status' => 'active',
        ]);
        $account->setCredentials(['api_key' => 'fake_customer_secret']);
        $account->save();

        $config = app(PaymentProviderConfigResolver::class)->resolve('simulator', $user);

        $this->assertSame('database', $config->source);
        $this->assertSame($account->id, $config->providerAccountId);
        $this->assertSame('fake_customer_secret', $config->credentials['api_key']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provider_account_not_accessible');

        app(PaymentProviderConfigResolver::class)->resolve('simulator', $other, $account);
    }

    public function test_disabled_provider_returns_stable_error(): void
    {
        config()->set('billing.providers.external_enabled', false);
        config()->set('billing.providers.platform.paypal.enabled', false);

        $config = app(PaymentProviderConfigResolver::class)->resolve('paypal');

        $this->assertFalse($config->enabled);
        $this->assertSame('disabled', $config->source);
        $this->assertSame('provider_disabled', $config->errorCode);
    }
}
