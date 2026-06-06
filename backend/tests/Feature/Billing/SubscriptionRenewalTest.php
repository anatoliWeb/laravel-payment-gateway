<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPaymentPreference;
use App\Services\Billing\SubscriptionLifecycleService;
use App\Services\Billing\WalletTransactionService;
use App\Services\Payments\PaymentSimulationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubscriptionRenewalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_wallet_renewal_renews_subscription_without_duplicate_payment(): void
    {
        $user = User::factory()->create();
        $this->activeCurrency();
        $plan = Plan::factory()->basic()->create(['price_amount' => 1000, 'currency' => 'USD']);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'current_period_end' => now()->subMinute(),
        ]);
        UserPaymentPreference::factory()->walletOnly()->create(['user_id' => $user->id]);
        app(WalletTransactionService::class)->credit($user, 'USD', 2000, idempotencyKey: 'renewal-wallet-credit');

        $service = app(SubscriptionLifecycleService::class);
        $first = $service->attemptRenewal($subscription);
        $second = $service->attemptRenewal($subscription->refresh());

        $this->assertTrue($first['renewed']);
        $this->assertSame('active', $subscription->refresh()->status);
        $this->assertSame(1, Payment::query()->where('subscription_id', $subscription->id)->count());
        $this->assertFalse($second['attempted']);
    }

    public function test_payment_method_renewal_waits_for_success_event(): void
    {
        $user = User::factory()->create();
        $this->activeCurrency();
        $plan = Plan::factory()->basic()->create(['price_amount' => 1000, 'currency' => 'USD']);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'current_period_end' => now()->subMinute(),
            'metadata' => ['auto_renew' => true],
        ]);
        $method = PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);
        UserPaymentPreference::factory()->paymentMethodOnly()->autoChargeEnabled()->create([
            'user_id' => $user->id,
            'default_payment_method_id' => $method->id,
        ]);

        $result = app(SubscriptionLifecycleService::class)->attemptRenewal($subscription);
        $payment = $result['payment'];

        $this->assertTrue($result['attempted']);
        $this->assertFalse($result['renewed']);
        $this->assertSame('processing', $payment->status);

        app(PaymentSimulationService::class)->simulateSuccess($payment, User::factory()->create());

        $this->assertSame('active', $subscription->refresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'billing.subscription_renewal_succeeded']);
    }

    public function test_failed_renewal_marks_subscription_past_due(): void
    {
        $user = User::factory()->create();
        $this->activeCurrency();
        $plan = Plan::factory()->basic()->create(['price_amount' => 1000, 'currency' => 'USD']);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'current_period_end' => now()->subMinute(),
            'metadata' => ['auto_renew' => true],
        ]);
        UserPaymentPreference::factory()->paymentMethodOnly()->autoChargeEnabled()->create([
            'user_id' => $user->id,
            'default_payment_method_id' => null,
        ]);

        $result = app(SubscriptionLifecycleService::class)->attemptRenewal($subscription);

        $this->assertTrue($result['attempted']);
        $this->assertFalse($result['renewed']);
        $this->assertSame('past_due', $subscription->refresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'billing.subscription_past_due']);
    }

    private function activeCurrency(): Currency
    {
        return Currency::query()->firstOrCreate(
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
    }
}
