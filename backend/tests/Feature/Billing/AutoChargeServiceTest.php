<?php

namespace Tests\Feature\Billing;

use App\Models\BillingRestriction;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPaymentPreference;
use App\Services\Billing\AutoChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoChargeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_auto_charge_blocks(): void
    {
        [$user] = $this->userReadyForAutoCharge(enabled: false);

        $result = app(AutoChargeService::class)->chargeWithDefaultMethod($user, 1000, 'USD', idempotencyKey: 'auto-charge-disabled');

        $this->assertFalse($result['allowed']);
        $this->assertSame('auto_charge_disabled', $result['reason']);
    }

    public function test_missing_consent_blocks(): void
    {
        [$user] = $this->userReadyForAutoCharge();
        UserPaymentPreference::query()->where('user_id', $user->id)->update(['auto_charge_consent_at' => null]);

        $result = app(AutoChargeService::class)->chargeWithDefaultMethod($user, 1000, 'USD', idempotencyKey: 'auto-charge-consent');

        $this->assertFalse($result['allowed']);
        $this->assertSame('auto_charge_consent_missing', $result['reason']);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'billing.auto_charge_consent_required',
        ]);
    }

    public function test_missing_or_inactive_default_method_blocks(): void
    {
        [$missingUser] = $this->userReadyForAutoCharge(withPaymentMethod: false);

        $missing = app(AutoChargeService::class)->chargeWithDefaultMethod($missingUser, 1000, 'USD', idempotencyKey: 'auto-charge-missing-method');

        $this->assertFalse($missing['allowed']);
        $this->assertSame('payment_method_not_found', $missing['reason']);

        [$inactiveUser] = $this->userReadyForAutoCharge(paymentMethodActive: false);

        $inactive = app(AutoChargeService::class)->chargeWithDefaultMethod($inactiveUser, 1000, 'USD', idempotencyKey: 'auto-charge-inactive-method');

        $this->assertFalse($inactive['allowed']);
        $this->assertSame('payment_method_inactive', $inactive['reason']);
    }

    public function test_invalid_amount_and_currency_block(): void
    {
        [$user] = $this->userReadyForAutoCharge();

        $invalidAmount = app(AutoChargeService::class)->chargeWithDefaultMethod($user, 0, 'USD', idempotencyKey: 'auto-charge-invalid-amount');
        $invalidCurrency = app(AutoChargeService::class)->chargeWithDefaultMethod($user, 1000, 'EUR', idempotencyKey: 'auto-charge-invalid-currency');

        $this->assertSame('invalid_amount', $invalidAmount['reason']);
        $this->assertSame('invalid_currency', $invalidCurrency['reason']);
    }

    public function test_valid_simulator_charge_creates_payment_without_subscription_activation(): void
    {
        [$user] = $this->userReadyForAutoCharge();
        $subscription = Subscription::factory()->pending()->create(['user_id' => $user->id]);

        $result = app(AutoChargeService::class)->chargeWithDefaultMethod(
            user: $user,
            amount: 2500,
            currencyCode: 'USD',
            metadata: ['subscription_id' => $subscription->id],
            idempotencyKey: 'auto-charge-success',
        );

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['attempted']);
        $this->assertNotNull($result['payment']);
        $this->assertSame('processing', $result['payment']->status);
        $this->assertSame('pending', $subscription->refresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'billing.auto_charge_succeeded',
        ]);
    }

    public function test_payment_blocked_user_cannot_auto_charge(): void
    {
        [$user] = $this->userReadyForAutoCharge();
        BillingRestriction::factory()->paymentBlocked()->create(['user_id' => $user->id]);

        $result = app(AutoChargeService::class)->chargeWithDefaultMethod($user, 1000, 'USD', idempotencyKey: 'auto-charge-risk');

        $this->assertFalse($result['allowed']);
        $this->assertSame('payment_risk_blocked', $result['reason']);
        $this->assertSame(0, Payment::query()->count());
    }

    private function userReadyForAutoCharge(
        bool $enabled = true,
        bool $withPaymentMethod = true,
        bool $paymentMethodActive = true,
    ): array {
        $user = User::factory()->create();
        Currency::query()->firstOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_precision' => 2,
                'is_active' => true,
                'is_base' => true,
                'description' => 'Test currency.',
                'metadata' => ['source' => 'test'],
            ],
        );
        $paymentMethod = null;

        if ($withPaymentMethod) {
            $paymentMethod = PaymentMethod::factory()
                ->fakeCard()
                ->default()
                ->create([
                    'user_id' => $user->id,
                    'status' => $paymentMethodActive ? 'active' : 'inactive',
                ]);
        }

        UserPaymentPreference::factory()->create([
            'user_id' => $user->id,
            'default_payment_method_id' => $paymentMethod?->id,
            'auto_charge_enabled' => $enabled,
            'auto_charge_consent_at' => now(),
        ]);

        return [$user, $paymentMethod];
    }
}
