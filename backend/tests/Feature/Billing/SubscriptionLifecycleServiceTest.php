<?php

namespace Tests\Feature\Billing;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubscriptionLifecycleServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_pending_subscription_and_change_plan_metadata(): void
    {
        $user = User::factory()->create();
        $basic = Plan::factory()->basic()->create();
        $pro = Plan::factory()->pro()->create();
        $service = app(SubscriptionLifecycleService::class);

        $subscription = $service->createPendingSubscription($user, $basic, [
            'idempotency_key' => 'pending-subscription-1',
        ]);
        $replay = $service->createPendingSubscription($user, $basic, [
            'idempotency_key' => 'pending-subscription-1',
        ]);

        $this->assertTrue($subscription->is($replay));
        $this->assertSame('pending', $subscription->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'billing.subscription_created']);

        $changed = $service->changePlan($subscription, $pro, $user, [
            'direction' => 'upgrade',
            'apply_immediately' => false,
        ]);

        $this->assertSame('upgrade', data_get($changed->metadata, 'pending_plan_change.type'));
        $this->assertSame($pro->id, data_get($changed->metadata, 'pending_plan_change.plan_id'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'billing.subscription_plan_upgrade_requested']);
    }

    public function test_activate_after_payment_is_idempotent(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->pending()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->succeeded()->create([
            'user_id' => $user->id,
            'payer_user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);
        $service = app(SubscriptionLifecycleService::class);

        $first = $service->activateAfterPayment($subscription, $payment);
        $firstPeriodEnd = $first->current_period_end?->toISOString();
        $second = $service->activateAfterPayment($subscription->refresh(), $payment);

        $this->assertSame('active', $second->status);
        $this->assertSame($firstPeriodEnd, $second->current_period_end?->toISOString());
        $this->assertSame($payment->id, data_get($second->metadata, 'last_successful_payment_id'));
    }

    public function test_downgrade_is_scheduled_for_period_end(): void
    {
        $user = User::factory()->create();
        $pro = Plan::factory()->pro()->create();
        $basic = Plan::factory()->basic()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $pro->id,
            'current_period_end' => now()->addMonth(),
        ]);

        $changed = app(SubscriptionLifecycleService::class)->changePlan($subscription, $basic, $user, [
            'direction' => 'downgrade',
        ]);

        $this->assertSame($pro->id, $changed->plan_id);
        $this->assertSame('downgrade', data_get($changed->metadata, 'pending_plan_change.type'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'billing.subscription_plan_downgrade_requested']);
    }
}
