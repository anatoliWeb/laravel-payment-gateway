<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\User;
use App\Models\UserPaymentPreference;
use App\Services\Billing\PaymentPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentPreferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_default_preferences_once(): void
    {
        $user = User::factory()->create();

        $first = app(PaymentPreferenceService::class)->getOrCreatePreferences($user);
        $second = app(PaymentPreferenceService::class)->getOrCreatePreferences($user);

        $this->assertTrue($first->is($second));
        $this->assertSame('wallet_first', $first->strategy);
        $this->assertFalse($first->auto_charge_enabled);
        $this->assertFalse($first->auto_top_up_enabled);
        $this->assertSame(1, UserPaymentPreference::query()->where('user_id', $user->id)->count());
    }

    public function test_it_sets_allowed_strategies(): void
    {
        $user = User::factory()->create();
        $service = app(PaymentPreferenceService::class);

        $this->assertSame('wallet_only', $service->setStrategy($user, 'wallet_only')->strategy);
        $this->assertSame('payment_method_only', $service->setStrategy($user, 'payment_method_only')->strategy);
        $this->assertSame('wallet_first', $service->setStrategy($user, 'wallet_first')->strategy);
        $this->assertSame('manual_invoice', $service->setStrategy($user, 'manual_invoice')->strategy);
    }

    public function test_it_rejects_invalid_strategy(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid_payment_strategy');

        app(PaymentPreferenceService::class)->setStrategy(User::factory()->create(), 'real_provider_first');
    }

    public function test_enable_and_disable_auto_charge_sets_consent_timestamp(): void
    {
        $user = User::factory()->create();
        $service = app(PaymentPreferenceService::class);

        $enabled = $service->enableAutoCharge($user);

        $this->assertTrue($enabled->auto_charge_enabled);
        $this->assertNotNull($enabled->auto_charge_consent_at);

        $disabled = $service->disableAutoCharge($user);

        $this->assertFalse($disabled->auto_charge_enabled);
        $this->assertNotNull($disabled->auto_charge_consent_at);
    }

    public function test_enable_and_disable_auto_top_up_sets_consent_and_currency_without_charging(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->usd()->base()->create();
        $service = app(PaymentPreferenceService::class);

        $enabled = $service->enableAutoTopUp($user, 1000, 5000, 'usd');

        $this->assertTrue($enabled->auto_top_up_enabled);
        $this->assertSame(1000, $enabled->auto_top_up_threshold_amount);
        $this->assertSame(5000, $enabled->auto_top_up_amount);
        $this->assertSame($currency->id, $enabled->auto_top_up_currency_id);
        $this->assertNotNull($enabled->auto_top_up_consent_at);

        $disabled = $service->disableAutoTopUp($user);

        $this->assertFalse($disabled->auto_top_up_enabled);
        $this->assertNotNull($disabled->auto_top_up_consent_at);
    }

    public function test_invalid_auto_top_up_currency_fails_safely(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('auto_top_up_currency_not_available');

        app(PaymentPreferenceService::class)->enableAutoTopUp(User::factory()->create(), 1000, 5000, 'USD');
    }
}
