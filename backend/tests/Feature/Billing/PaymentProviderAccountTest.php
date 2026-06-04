<?php

namespace Tests\Feature\Billing;

use App\Models\PaymentProviderAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentProviderAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_encrypted_and_masked(): void
    {
        $account = PaymentProviderAccount::factory()->create();
        $account->setCredentials([
            'api_key' => 'fake_customer_api_key_1234',
            'webhook' => ['secret' => 'fake_webhook_secret_9876'],
        ]);
        $account->save();

        $raw = $account->getRawOriginal('encrypted_credentials');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('fake_customer_api_key_1234', $raw);
        $this->assertSame('fake_customer_api_key_1234', $account->getCredentials()['api_key']);
        $this->assertStringNotContainsString('fake_customer_api_key_1234', $account->getMaskedCredentials()['api_key']);
        $this->assertStringEndsWith('1234', $account->getMaskedCredentials()['api_key']);
        $this->assertArrayNotHasKey('encrypted_credentials', $account->toArray());
    }

    public function test_raw_payment_data_is_rejected_from_credentials(): void
    {
        $account = PaymentProviderAccount::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('provider_credentials_contain_payment_data');

        $account->setCredentials(['card_number' => '4242424242424242']);
    }

    public function test_provider_accounts_belong_to_one_user(): void
    {
        $user = User::factory()->create();
        $account = PaymentProviderAccount::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($account->user->is($user));
        $this->assertTrue($user->paymentProviderAccounts->first()->is($account));
    }
}
