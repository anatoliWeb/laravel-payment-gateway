<?php

namespace Tests\Feature\Billing;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentSimulationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubscriptionActivationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_successful_payment_activates_pending_subscription(): void
    {
        $actor = User::factory()->create();
        $subscription = Subscription::factory()->pending()->create([
            'current_period_start' => null,
            'current_period_end' => null,
        ]);
        $payment = Payment::factory()->processing()->create([
            'user_id' => $subscription->user_id,
            'payer_user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
        ]);

        app(PaymentSimulationService::class)->simulateSuccess($payment, $actor);

        $this->assertSame('active', $subscription->refresh()->status);
        $this->assertNotNull($subscription->current_period_start);
        $this->assertNotNull($subscription->current_period_end);
        $this->assertDatabaseHas('activity_logs', ['action' => 'billing.subscription_activated']);
    }

    public function test_failed_payment_does_not_activate_initial_subscription(): void
    {
        $actor = User::factory()->create();
        $subscription = Subscription::factory()->pending()->create([
            'current_period_start' => null,
            'current_period_end' => null,
        ]);
        $payment = Payment::factory()->processing()->create([
            'user_id' => $subscription->user_id,
            'payer_user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
        ]);

        app(PaymentSimulationService::class)->simulateFailure($payment, $actor, 'card_declined');

        $this->assertSame('pending', $subscription->refresh()->status);
        $this->assertNull($subscription->current_period_start);
        $this->assertDatabaseHas('activity_logs', ['action' => 'billing.subscription_payment_failed']);
    }

    public function test_invoice_linked_payment_activates_subscription(): void
    {
        $actor = User::factory()->create();
        $subscription = Subscription::factory()->pending()->create();
        $payment = Payment::factory()->processing()->create([
            'user_id' => $subscription->user_id,
            'payer_user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'invoice_id' => null,
        ]);

        app(PaymentSimulationService::class)->simulateSuccess($payment, $actor);

        $this->assertSame('active', $subscription->refresh()->status);
    }
}
