<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\PaymentProviderAccount;
use App\Models\Seller;
use App\Models\User;
use App\Services\Payments\Providers\PaymentProviderConfigResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class PaymentProviderConfigResolverTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_seller_then_company_then_user_provider_account_priority_and_scope_isolation(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $seller = Seller::factory()->create(['company_id' => $company->id]);
        $userAccount = PaymentProviderAccount::factory()->create(['user_id' => $user->id]);
        $companyAccount = PaymentProviderAccount::factory()->create([
            'company_id' => $company->id,
            'seller_id' => null,
        ]);
        $sellerAccount = PaymentProviderAccount::factory()->create([
            'company_id' => $company->id,
            'seller_id' => $seller->id,
        ]);
        $resolver = app(PaymentProviderConfigResolver::class);

        $this->assertSame($sellerAccount->id, $resolver->resolve('simulator', $user, company: $company, seller: $seller)->providerAccountId);
        $this->assertSame($companyAccount->id, $resolver->resolve('simulator', $user, company: $company)->providerAccountId);
        $this->assertSame($userAccount->id, $resolver->resolve('simulator', $user)->providerAccountId);

        PaymentProviderAccount::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'seller_id' => $seller->id,
        ]);

        $this->assertSame($sellerAccount->id, $resolver->resolve('simulator', $user, company: $company, seller: $seller)->providerAccountId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provider_account_not_accessible');

        $resolver->resolve('simulator', $user, $sellerAccount, Company::factory()->create());
    }
}
