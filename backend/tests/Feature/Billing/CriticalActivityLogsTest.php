<?php

namespace Tests\Feature\Billing;

use App\Jobs\Payments\SendWebhookDeliveryJob;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\SubscriptionLifecycleService;
use App\Services\Chat\ChatBillingGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CriticalActivityLogsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_and_idempotency_activity_logs_are_written_with_safe_metadata(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);
        $payload = [
            'amount' => 1200,
            'currency' => 'USD',
            'payment_source' => 'payment_method',
            'metadata' => [
                'safe_context' => 'phase20',
            ],
        ];

        $this->withHeader('Idempotency-Key', 'phase20-raw-idempotency-key')
            ->postJson('/api/v1/billing/payments', $payload)
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'phase20-raw-idempotency-key')
            ->postJson('/api/v1/billing/payments', $payload)
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'phase20-raw-idempotency-key')
            ->postJson('/api/v1/billing/payments', array_merge($payload, ['amount' => 1300]))
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'idempotency_key_conflict');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'billing.payment_created',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'billing.idempotency_replayed',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'billing.idempotency_conflict',
        ]);
        $this->assertActivityMetadataIsSafe();
    }

    public function test_payment_simulation_and_webhook_activity_logs_are_written(): void
    {
        Http::fake([
            'client.example.test/*' => Http::sequence()
                ->push('ok', 204)
                ->push('temporary failure', 500),
        ]);
        $this->actorWithPermission('billing.payments.simulate');

        $success = Payment::factory()->processing()->create([
            'subscription_id' => null,
            'callback_url' => 'https://client.example.test/billing/success',
        ]);
        $failure = Payment::factory()->processing()->create([
            'subscription_id' => null,
            'callback_url' => 'https://client.example.test/billing/failure',
        ]);

        $this->postJson("/api/v1/billing/payments/{$success->id}/simulate/success")
            ->assertOk();
        $this->postJson("/api/v1/billing/payments/{$failure->id}/simulate/failure", [
            'reason' => 'card_declined',
        ])->assertOk();

        $successDelivery = $success->refresh()->webhookDeliveries()->firstOrFail();
        $failureDelivery = $failure->refresh()->webhookDeliveries()->firstOrFail();

        (new SendWebhookDeliveryJob($successDelivery->id))->handle(app(\App\Services\Payments\WebhookDeliveryService::class));
        (new SendWebhookDeliveryJob($failureDelivery->id))->handle(app(\App\Services\Payments\WebhookDeliveryService::class));

        foreach ([
            'billing.payment_simulated_success',
            'billing.payment_simulated_failure',
            'billing.webhook_dispatched',
            'billing.webhook_delivered',
            'billing.webhook_failed',
        ] as $action) {
            $this->assertDatabaseHas('activity_logs', ['action' => $action]);
        }
    }

    public function test_subscription_activity_logs_are_written(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->basic()->create();
        $service = app(SubscriptionLifecycleService::class);

        $subscription = $service->createPendingSubscription($user, $plan, [
            'idempotency_key' => 'phase20-subscription-create',
        ]);
        $payment = Payment::factory()->succeeded()->create([
            'user_id' => $user->id,
            'payer_user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'amount' => $plan->price_amount,
            'currency' => $plan->currency,
        ]);

        $service->activateAfterPayment($subscription, $payment);
        $service->cancelSubscription($subscription->refresh(), $user, 'phase20 audit', true);

        foreach ([
            'billing.subscription_created',
            'billing.subscription_activated',
            'billing.subscription_cancelled',
        ] as $action) {
            $this->assertDatabaseHas('activity_logs', ['action' => $action]);
        }
    }

    public function test_usage_limit_activity_log_is_written(): void
    {
        $user = User::factory()->create();

        app(ChatBillingGuard::class)->limitExceededResponse($user, 'message.create', [
            'feature_key' => 'chat.messages.daily',
            'reason' => 'limit_exceeded',
            'usage' => [
                'limit' => 1,
                'used' => 1,
                'remaining' => 0,
                'period' => 'daily',
                'reset_at' => now()->addDay(),
            ],
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'chat.feature_limit_exceeded',
        ]);
    }

    private function assertActivityMetadataIsSafe(): void
    {
        $encoded = ActivityLog::query()
            ->whereIn('action', [
                'billing.payment_created',
                'billing.idempotency_replayed',
                'billing.idempotency_conflict',
            ])
            ->get()
            ->map(fn (ActivityLog $log): string => json_encode($log->meta, JSON_THROW_ON_ERROR))
            ->implode("\n");

        $this->assertStringNotContainsString('phase20-raw-idempotency-key', $encoded);
        $this->assertStringNotContainsString('4242424242424242', $encoded);
        $this->assertStringNotContainsString('cvv', strtolower($encoded));
        $this->assertStringNotContainsString('provider_secret', strtolower($encoded));
        $this->assertStringNotContainsString('private_key', strtolower($encoded));
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function actorWithPermission(string $permissionName): User
    {
        $actor = User::factory()->create();
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            ['description' => $permissionName],
        );
        $actor->permissions()->syncWithoutDetaching([$permission->id]);
        Sanctum::actingAs($actor);

        return $actor;
    }

    private function activeCurrency(string $code): Currency
    {
        return Currency::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => "{$code} Currency",
                'symbol' => $code,
                'decimal_precision' => 2,
                'is_active' => true,
                'is_base' => $code === 'USD',
                'description' => 'Test currency.',
                'metadata' => ['source' => 'test'],
            ],
        );
    }
}
