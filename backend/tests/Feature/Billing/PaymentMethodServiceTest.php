<?php

namespace Tests\Feature\Billing;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Billing\PaymentMethodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentMethodServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_fake_card_with_last4_only(): void
    {
        $user = User::factory()->create();

        $paymentMethod = app(PaymentMethodService::class)->createFakeCard($user, [
            'brand' => 'mastercard',
            'last4' => '1111',
            'exp_month' => 10,
            'exp_year' => 2031,
            'metadata' => [
                'source' => 'test',
                'cvv' => '123',
                'token' => 'secret-token',
            ],
        ]);

        $this->assertSame('fake_card', $paymentMethod->type);
        $this->assertSame('simulator', $paymentMethod->provider);
        $this->assertSame('mastercard', $paymentMethod->brand);
        $this->assertSame('1111', $paymentMethod->last4);
        $this->assertSame('Mastercard ending 1111', $paymentMethod->display_name);
        $this->assertArrayHasKey('source', $paymentMethod->metadata);
        $this->assertArrayNotHasKey('cvv', $paymentMethod->metadata);
        $this->assertArrayNotHasKey('token', $paymentMethod->metadata);
    }

    public function test_it_rejects_raw_card_data(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('raw_card_data_not_allowed');

        app(PaymentMethodService::class)->createFakeCard(User::factory()->create(), [
            'card_number' => '4242424242424242',
            'last4' => '4242',
        ]);
    }

    public function test_it_creates_manual_invoice_and_wallet_methods(): void
    {
        $user = User::factory()->create();
        $service = app(PaymentMethodService::class);

        $manual = $service->createManualInvoiceMethod($user);
        $wallet = $service->createWalletMethod($user);

        $this->assertSame('fake_manual_invoice', $manual->type);
        $this->assertSame('manual', $manual->provider);
        $this->assertSame('fake_wallet', $wallet->type);
        $this->assertSame('internal_wallet', $wallet->provider);
        $this->assertSame(2, PaymentMethod::query()->where('user_id', $user->id)->count());
    }

    public function test_set_default_unsets_previous_default_and_syncs_preference(): void
    {
        $user = User::factory()->create();
        $first = PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);
        $second = PaymentMethod::factory()->fakeWallet()->create(['user_id' => $user->id]);

        $default = app(PaymentMethodService::class)->setDefaultPaymentMethod($user, $second);

        $this->assertTrue($default->is_default);
        $this->assertFalse($first->refresh()->is_default);
        $this->assertSame($second->id, $user->refresh()->paymentPreference->default_payment_method_id);
    }

    public function test_cannot_set_another_users_payment_method_as_default(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payment_method_does_not_belong_to_user');

        $user = User::factory()->create();
        $otherMethod = PaymentMethod::factory()->create();

        app(PaymentMethodService::class)->setDefaultPaymentMethod($user, $otherMethod);
    }

    public function test_it_deactivates_payment_method_and_clears_default_preference(): void
    {
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->fakeCard()->create(['user_id' => $user->id]);

        app(PaymentMethodService::class)->setDefaultPaymentMethod($user, $paymentMethod);
        $deactivated = app(PaymentMethodService::class)->deactivatePaymentMethod($user, $paymentMethod);

        $this->assertSame('inactive', $deactivated->status);
        $this->assertFalse($deactivated->is_default);
        $this->assertNull($user->refresh()->paymentPreference->default_payment_method_id);
    }
}
