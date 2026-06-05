<?php

namespace Tests\Feature\Billing;

use App\Jobs\Payments\SendWebhookDeliveryJob;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Seller;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookDeliveryFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_success_simulation_creates_payment_succeeded_webhook_delivery(): void
    {
        Bus::fake();
        $this->actorWithPermission('billing.payments.simulate');
        $payment = Payment::factory()->processing()->create([
            'subscription_id' => null,
            'callback_url' => 'https://client.example.test/billing/webhooks',
        ]);

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertOk();

        $delivery = WebhookDelivery::query()->first();

        $this->assertNotNull($delivery);
        $this->assertSame('payment.succeeded', $delivery->event);
        $this->assertSame('pending', $delivery->status);
        $this->assertSame($payment->id, $delivery->payment_id);
        $this->assertSame('https://client.example.test/billing/webhooks', $delivery->url);
        Bus::assertDispatched(SendWebhookDeliveryJob::class);
    }

    public function test_failure_simulation_creates_payment_failed_webhook_delivery(): void
    {
        Bus::fake();
        $this->actorWithPermission('billing.payments.simulate');
        $payment = Payment::factory()->processing()->create(['subscription_id' => null]);

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/failure", [
            'reason' => 'card_declined',
        ])->assertOk();

        $this->assertDatabaseHas('webhook_deliveries', [
            'payment_id' => $payment->id,
            'event' => 'payment.failed',
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }

    public function test_no_callback_url_means_no_delivery_record(): void
    {
        Bus::fake();
        $this->actorWithPermission('billing.payments.simulate');
        $payment = Payment::factory()->processing()->create([
            'subscription_id' => null,
            'callback_url' => null,
        ]);

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertOk();

        $this->assertSame(0, WebhookDelivery::query()->count());
        Bus::assertNotDispatched(SendWebhookDeliveryJob::class);
    }

    public function test_payload_is_safe_and_includes_ownership_fields(): void
    {
        Bus::fake();
        $this->actorWithPermission('billing.payments.simulate');
        $payer = User::factory()->create();
        $company = Company::factory()->create();
        $seller = Seller::factory()->create(['company_id' => $company->id]);
        $payment = Payment::factory()->processing()->create([
            'user_id' => $payer->id,
            'payer_user_id' => $payer->id,
            'subscription_id' => null,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'metadata' => [
                'source' => 'test',
                'idempotency_key_hash' => 'do_not_expose',
                'secret' => 'unsafe',
            ],
        ]);

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertOk();

        $payload = WebhookDelivery::query()->firstOrFail()->payload;

        $this->assertSame('payment.succeeded', $payload['event_type']);
        $this->assertSame($payment->uuid, $payload['payment']['uuid']);
        $this->assertSame($payer->id, $payload['payment']['payer_user_id']);
        $this->assertSame($company->id, $payload['payment']['company_id']);
        $this->assertSame($seller->id, $payload['payment']['seller_id']);
        $this->assertArrayNotHasKey('idempotency_key_hash', $payload['payment']['metadata']);
        $this->assertArrayNotHasKey('secret', $payload['payment']['metadata']);
    }

    public function test_signature_header_is_generated(): void
    {
        Bus::fake();
        $this->actorWithPermission('billing.payments.simulate');
        $payment = Payment::factory()->processing()->create(['subscription_id' => null]);

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertOk();

        $headers = WebhookDelivery::query()->firstOrFail()->metadata['headers'];

        $this->assertArrayHasKey('X-Billing-Event', $headers);
        $this->assertArrayHasKey('X-Billing-Delivery', $headers);
        $this->assertArrayHasKey('X-Billing-Signature', $headers);
        $this->assertArrayHasKey('X-Billing-Timestamp', $headers);
        $this->assertSame(64, strlen($headers['X-Billing-Signature']));
    }

    public function test_job_marks_delivery_delivered_on_successful_response(): void
    {
        Http::fake([
            'client.example.test/*' => Http::response('ok', 204),
        ]);
        $payment = Payment::factory()->create(['subscription_id' => null]);
        $delivery = WebhookDelivery::factory()->create([
            'payment_id' => $payment->id,
            'subscription_id' => null,
            'url' => 'https://client.example.test/billing/webhooks',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 5,
        ]);

        (new SendWebhookDeliveryJob($delivery->id))->handle(
            app(\App\Services\Payments\WebhookDeliveryService::class),
        );

        $delivery->refresh();
        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(204, $delivery->response_status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_job_marks_delivery_retrying_on_non_2xx_response(): void
    {
        Http::fake([
            'client.example.test/*' => Http::response('temporary failure', 500),
        ]);
        $payment = Payment::factory()->create(['subscription_id' => null]);
        $delivery = WebhookDelivery::factory()->create([
            'payment_id' => $payment->id,
            'subscription_id' => null,
            'url' => 'https://client.example.test/billing/webhooks',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 5,
        ]);

        (new SendWebhookDeliveryJob($delivery->id))->handle(
            app(\App\Services\Payments\WebhookDeliveryService::class),
        );

        $delivery->refresh();
        $this->assertSame('retrying', $delivery->status);
        $this->assertSame(500, $delivery->response_status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->next_retry_at);
    }

    public function test_job_marks_permanently_failed_after_max_attempts(): void
    {
        Http::fake([
            'client.example.test/*' => Http::response('still failing', 500),
        ]);
        $payment = Payment::factory()->create(['subscription_id' => null]);
        $delivery = WebhookDelivery::factory()->create([
            'payment_id' => $payment->id,
            'subscription_id' => null,
            'url' => 'https://client.example.test/billing/webhooks',
            'status' => 'retrying',
            'attempts' => 4,
            'max_attempts' => 5,
        ]);

        (new SendWebhookDeliveryJob($delivery->id))->handle(
            app(\App\Services\Payments\WebhookDeliveryService::class),
        );

        $delivery->refresh();
        $this->assertSame('permanently_failed', $delivery->status);
        $this->assertSame(5, $delivery->attempts);
        $this->assertNull($delivery->next_retry_at);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'billing.webhook_permanently_failed',
        ]);
    }

    public function test_repeated_final_simulation_does_not_duplicate_webhook_delivery(): void
    {
        Bus::fake();
        $this->actorWithPermission('billing.payments.simulate');
        $payment = Payment::factory()->processing()->create(['subscription_id' => null]);

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertOk();
        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertOk();

        $this->assertSame(1, WebhookDelivery::query()->where('payment_id', $payment->id)->count());
    }

    public function test_no_real_external_http_call_is_made_in_tests(): void
    {
        Http::fake([
            '*' => Http::response('ok', 200),
        ]);
        $payment = Payment::factory()->create(['subscription_id' => null]);
        $delivery = WebhookDelivery::factory()->create([
            'payment_id' => $payment->id,
            'subscription_id' => null,
        ]);

        (new SendWebhookDeliveryJob($delivery->id))->handle(
            app(\App\Services\Payments\WebhookDeliveryService::class),
        );

        Http::assertSentCount(1);
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
