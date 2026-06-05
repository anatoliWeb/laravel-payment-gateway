<?php

namespace Tests\Feature\Billing;

use App\Jobs\Payments\SendWebhookDeliveryJob;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookRetryApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_with_permission_can_retry_failed_delivery(): void
    {
        Bus::fake();
        $actor = $this->actorWithPermission('billing.webhooks.retry');
        $payment = Payment::factory()->create([
            'user_id' => $actor->id,
            'payer_user_id' => $actor->id,
            'subscription_id' => null,
        ]);
        $delivery = WebhookDelivery::factory()->failed()->create([
            'payment_id' => $payment->id,
            'subscription_id' => null,
        ]);

        $this->postJson("/api/v1/billing/webhooks/{$delivery->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $delivery->refresh();
        $this->assertSame('pending', $delivery->status);
        $this->assertNull($delivery->response_status);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'billing.webhook_retry_requested',
        ]);
        Bus::assertDispatched(SendWebhookDeliveryJob::class);
    }

    public function test_user_without_permission_cannot_retry_delivery(): void
    {
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);
        $payment = Payment::factory()->create(['subscription_id' => null]);
        $delivery = WebhookDelivery::factory()->failed()->create([
            'payment_id' => $payment->id,
            'subscription_id' => null,
        ]);

        $this->postJson("/api/v1/billing/webhooks/{$delivery->id}/retry")
            ->assertForbidden();
    }

    public function test_delivered_delivery_cannot_be_retried(): void
    {
        Bus::fake();
        $actor = $this->actorWithPermission('billing.webhooks.retry');
        $payment = Payment::factory()->create([
            'user_id' => $actor->id,
            'payer_user_id' => $actor->id,
            'subscription_id' => null,
        ]);
        $delivery = WebhookDelivery::factory()->delivered()->create([
            'payment_id' => $payment->id,
            'subscription_id' => null,
        ]);

        $this->postJson("/api/v1/billing/webhooks/{$delivery->id}/retry")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'webhook_retry_not_allowed');

        Bus::assertNotDispatched(SendWebhookDeliveryJob::class);
    }

    public function test_listing_endpoint_returns_safe_webhook_delivery_history(): void
    {
        $actor = $this->actorWithPermission('billing.webhooks.view');
        $payment = Payment::factory()->create([
            'user_id' => $actor->id,
            'payer_user_id' => $actor->id,
            'subscription_id' => null,
        ]);
        WebhookDelivery::factory()->failed()->create([
            'payment_id' => $payment->id,
            'subscription_id' => null,
            'response_body' => 'temporary error',
            'url' => 'https://client.example.test/webhook',
        ]);

        $this->getJson("/api/v1/billing/payments/{$payment->id}/webhooks")
            ->assertOk()
            ->assertJsonPath('data.0.event_type', 'payment.created')
            ->assertJsonPath('data.0.callback_host', 'client.example.test')
            ->assertJsonMissing(['url' => 'https://client.example.test/webhook']);
    }

    public function test_user_without_view_permission_cannot_list_delivery_history(): void
    {
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);
        $payment = Payment::factory()->create([
            'user_id' => $actor->id,
            'payer_user_id' => $actor->id,
            'subscription_id' => null,
        ]);

        $this->getJson("/api/v1/billing/payments/{$payment->id}/webhooks")
            ->assertForbidden();
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
}
