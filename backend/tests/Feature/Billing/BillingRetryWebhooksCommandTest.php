<?php

namespace Tests\Feature\Billing;

use App\Jobs\Payments\SendWebhookDeliveryJob;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BillingRetryWebhooksCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_due_retryable_webhook_is_queued_and_dispatched(): void
    {
        Bus::fake();

        $due = WebhookDelivery::factory()->failed()->create([
            'status' => 'retrying',
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
        ]);
        $delivered = WebhookDelivery::factory()->delivered()->create();
        $permanent = WebhookDelivery::factory()->permanentlyFailed()->create();
        $maxed = WebhookDelivery::factory()->failed()->create([
            'attempts' => 3,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
        ]);

        $this->artisan('billing:retry-webhooks')
            ->expectsOutputToContain('Billing Retry Webhooks')
            ->assertExitCode(0);

        $this->assertSame('queued', $due->fresh()->status);
        $this->assertSame('delivered', $delivered->fresh()->status);
        $this->assertSame('permanently_failed', $permanent->fresh()->status);
        $this->assertSame('failed', $maxed->fresh()->status);
        Bus::assertDispatched(SendWebhookDeliveryJob::class, fn (SendWebhookDeliveryJob $job): bool => $job->webhookDeliveryId === $due->id);
        Bus::assertDispatchedTimes(SendWebhookDeliveryJob::class, 1);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'billing.scheduler.retry_webhooks',
        ]);
    }
}
