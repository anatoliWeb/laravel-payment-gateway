<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_create_subscription_and_payment_attempt(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
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
        $plan = Plan::factory()->basic()->create(['price_amount' => 2900, 'currency' => 'USD']);
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);

        $response = $this->withHeader('Idempotency-Key', 'subscription-api-create-1')
            ->postJson('/api/v1/billing/subscriptions', [
                'plan_id' => $plan->id,
                'payment_source' => 'payment_method',
                'auto_renew' => true,
            ])->assertCreated()
            ->assertJsonPath('data.subscription.status', 'pending')
            ->assertJsonPath('data.payment.status', 'processing');

        $this->assertSame($plan->id, $response->json('data.subscription.plan_id'));
    }

    public function test_user_can_cancel_own_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/billing/subscriptions/{$subscription->id}/cancel", [
            'immediate' => true,
            'reason' => 'API cancellation',
        ])->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }
}
