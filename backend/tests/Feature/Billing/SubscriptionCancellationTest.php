<?php

namespace Tests\Feature\Billing;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionCancellationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cancel_subscription_at_period_end_and_immediate(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        $service = app(SubscriptionLifecycleService::class);

        $periodEnd = $service->cancelSubscription($subscription, $user, 'no longer needed');
        $this->assertSame('active', $periodEnd->status);
        $this->assertTrue((bool) $periodEnd->cancel_at_period_end);

        $immediate = $service->cancelSubscription($periodEnd, $user, 'stop now', immediate: true);
        $this->assertSame('cancelled', $immediate->status);
        $this->assertNotNull($immediate->ended_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'billing.subscription_cancelled']);
    }

    public function test_user_cannot_cancel_unrelated_subscription_through_api(): void
    {
        $actor = User::factory()->create();
        $other = Subscription::factory()->create();
        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/billing/subscriptions/{$other->id}/cancel", [
            'immediate' => true,
        ])->assertStatus(404)
            ->assertJsonPath('errors.code', 'subscription_not_found');
    }
}
