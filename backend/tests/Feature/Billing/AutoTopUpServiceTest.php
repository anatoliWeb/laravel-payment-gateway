<?php

namespace Tests\Feature\Billing;

use App\Models\BillingRestriction;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\UserPaymentPreference;
use App\Services\Billing\AutoTopUpService;
use App\Services\Billing\WalletService;
use App\Services\Billing\WalletTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoTopUpServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_auto_top_up_does_not_attempt_payment(): void
    {
        [$user] = $this->userReadyForAutoTopUp(enabled: false);

        $result = app(AutoTopUpService::class)->attemptAutoTopUp($user, 'USD', 'auto-top-up-disabled');

        $this->assertFalse($result['allowed']);
        $this->assertSame('auto_top_up_disabled', $result['reason']);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_missing_consent_blocks_auto_top_up(): void
    {
        [$user, $currency] = $this->userReadyForAutoTopUp();
        UserPaymentPreference::query()->where('user_id', $user->id)->update([
            'auto_top_up_consent_at' => null,
            'auto_top_up_currency_id' => $currency->id,
        ]);

        $result = app(AutoTopUpService::class)->attemptAutoTopUp($user, 'USD', 'auto-top-up-consent');

        $this->assertFalse($result['allowed']);
        $this->assertSame('auto_top_up_consent_missing', $result['reason']);
    }

    public function test_balance_above_threshold_does_not_top_up(): void
    {
        [$user] = $this->userReadyForAutoTopUp();
        app(WalletTransactionService::class)->credit($user, 'USD', 2000);

        $result = app(AutoTopUpService::class)->attemptAutoTopUp($user, 'USD', 'auto-top-up-threshold');

        $this->assertFalse($result['allowed']);
        $this->assertSame('balance_above_threshold', $result['reason']);
    }

    public function test_balance_at_or_below_threshold_attempts_top_up_and_credits_wallet(): void
    {
        [$user] = $this->userReadyForAutoTopUp();

        $result = app(AutoTopUpService::class)->attemptAutoTopUp($user, 'USD', 'auto-top-up-success');
        $balance = app(WalletService::class)->getBalance($user->refresh(), 'USD');

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['attempted']);
        $this->assertNotNull($result['payment']);
        $this->assertNotNull($result['wallet_transaction']);
        $this->assertSame(5000, $balance->available_amount);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'billing.auto_top_up_succeeded',
        ]);
    }

    public function test_repeated_auto_top_up_key_replays_without_duplicate_payment_or_credit(): void
    {
        [$user] = $this->userReadyForAutoTopUp();

        $first = app(AutoTopUpService::class)->attemptAutoTopUp($user, 'USD', 'auto-top-up-replay');
        $second = app(AutoTopUpService::class)->attemptAutoTopUp($user, 'USD', 'auto-top-up-replay');

        $this->assertTrue($second['allowed']);
        $this->assertTrue($first['payment']->is($second['payment']));
        $this->assertTrue($first['wallet_transaction']->is($second['wallet_transaction']));
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(5000, app(WalletService::class)->getBalance($user->refresh(), 'USD')->available_amount);
    }

    public function test_missing_or_inactive_payment_method_blocks_auto_top_up(): void
    {
        [$missingUser] = $this->userReadyForAutoTopUp(withPaymentMethod: false);

        $missing = app(AutoTopUpService::class)->attemptAutoTopUp($missingUser, 'USD', 'auto-top-up-missing-method');

        $this->assertFalse($missing['allowed']);
        $this->assertSame('payment_method_not_found', $missing['reason']);

        [$inactiveUser] = $this->userReadyForAutoTopUp(paymentMethodActive: false);

        $inactive = app(AutoTopUpService::class)->attemptAutoTopUp($inactiveUser, 'USD', 'auto-top-up-inactive-method');

        $this->assertFalse($inactive['allowed']);
        $this->assertSame('payment_method_inactive', $inactive['reason']);
    }

    public function test_daily_and_monthly_limits_block_auto_top_up(): void
    {
        [$dailyUser] = $this->userReadyForAutoTopUp(maxPerDay: 1, maxPerMonth: null);
        app(AutoTopUpService::class)->attemptAutoTopUp($dailyUser, 'USD', 'auto-top-up-daily-first');

        $daily = app(AutoTopUpService::class)->attemptAutoTopUp($dailyUser, 'USD', 'auto-top-up-daily-second');

        $this->assertFalse($daily['allowed']);
        $this->assertSame('auto_top_up_daily_limit_exceeded', $daily['reason']);

        [$monthlyUser] = $this->userReadyForAutoTopUp(maxPerDay: null, maxPerMonth: 1);
        app(AutoTopUpService::class)->attemptAutoTopUp($monthlyUser, 'USD', 'auto-top-up-monthly-first');

        $monthly = app(AutoTopUpService::class)->attemptAutoTopUp($monthlyUser, 'USD', 'auto-top-up-monthly-second');

        $this->assertFalse($monthly['allowed']);
        $this->assertSame('auto_top_up_monthly_limit_exceeded', $monthly['reason']);
    }

    public function test_payment_blocked_user_cannot_auto_top_up(): void
    {
        [$user] = $this->userReadyForAutoTopUp();
        BillingRestriction::factory()->paymentBlocked()->create(['user_id' => $user->id]);

        $result = app(AutoTopUpService::class)->attemptAutoTopUp($user, 'USD', 'auto-top-up-risk-blocked');

        $this->assertFalse($result['allowed']);
        $this->assertSame('payment_risk_blocked', $result['reason']);
        $this->assertSame(0, Payment::query()->count());
    }

    private function userReadyForAutoTopUp(
        bool $enabled = true,
        bool $withPaymentMethod = true,
        bool $paymentMethodActive = true,
        ?int $maxPerDay = 2,
        ?int $maxPerMonth = 10,
    ): array {
        $user = User::factory()->create();
        $currency = Currency::query()->firstOrCreate(
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
            'auto_top_up_enabled' => $enabled,
            'auto_top_up_threshold_amount' => 1000,
            'auto_top_up_amount' => 5000,
            'auto_top_up_currency_id' => $currency->id,
            'max_auto_top_up_per_day' => $maxPerDay,
            'max_auto_top_up_per_month' => $maxPerMonth,
            'auto_top_up_consent_at' => now(),
        ]);

        return [$user, $currency, $paymentMethod];
    }
}
