<?php

namespace Tests\Feature\Billing;

use App\Events\Billing\PaymentSucceeded;
use App\Jobs\Billing\GenerateBillingReceiptJob;
use App\Jobs\Billing\NotifySellerCompanyBillingEventJob;
use App\Jobs\Billing\SendBillingEmailNotificationJob;
use App\Jobs\Billing\SendBillingSmsNotificationJob;
use App\Jobs\Payments\SendWebhookDeliveryJob;
use App\Listeners\Billing\DispatchBillingWebhookAction;
use App\Listeners\Billing\DispatchPaymentNotificationActions;
use App\Listeners\Billing\DispatchReceiptGenerationAction;
use App\Listeners\Billing\DispatchSellerCompanyNotificationAction;
use App\Models\Payment;
use App\Models\Seller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BillingPostEventActionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_success_can_queue_placeholder_post_event_actions(): void
    {
        Queue::fake();
        $payment = Payment::factory()->succeeded()->create();
        $event = new PaymentSucceeded($payment);

        app(DispatchReceiptGenerationAction::class)->handle($event);
        app(DispatchPaymentNotificationActions::class)->handle($event);

        Queue::assertPushed(GenerateBillingReceiptJob::class);
        Queue::assertPushed(SendBillingSmsNotificationJob::class);
        Queue::assertPushed(SendBillingEmailNotificationJob::class);
    }

    public function test_seller_company_notification_queues_only_for_scoped_payloads(): void
    {
        Queue::fake();
        $seller = Seller::factory()->create();
        $scoped = new PaymentSucceeded(Payment::factory()->succeeded()->create([
            'company_id' => $seller->company_id,
            'seller_id' => $seller->id,
        ]));
        $unscoped = new PaymentSucceeded(Payment::factory()->succeeded()->create([
            'company_id' => null,
            'seller_id' => null,
        ]));

        app(DispatchSellerCompanyNotificationAction::class)->handle($scoped);
        app(DispatchSellerCompanyNotificationAction::class)->handle($unscoped);

        Queue::assertPushed(NotifySellerCompanyBillingEventJob::class, 1);
    }

    public function test_webhook_listener_does_not_duplicate_phase_16_webhook_jobs(): void
    {
        Queue::fake();
        $event = new PaymentSucceeded(Payment::factory()->succeeded()->create());

        app(DispatchBillingWebhookAction::class)->handle($event);

        Queue::assertNotPushed(SendWebhookDeliveryJob::class);
    }

    public function test_post_event_payload_does_not_contain_secrets(): void
    {
        $event = new PaymentSucceeded(Payment::factory()->succeeded()->create([
            'metadata' => ['secret' => 'hidden', 'idempotency_key' => 'raw-key'],
        ]));
        $encoded = json_encode($event->payload, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertStringNotContainsString('idempotency', $encoded);
        $this->assertStringNotContainsString('card_number', $encoded);
    }
}
