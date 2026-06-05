<?php

namespace Tests\Feature\Billing;

use App\Events\Billing\InvoiceFailed;
use App\Events\Billing\InvoiceIssued;
use App\Events\Billing\InvoicePaid;
use App\Events\Billing\InvoicePaymentPending;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BillingInvoiceEventsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invoice_lifecycle_events_are_dispatched(): void
    {
        Event::fake([
            InvoiceIssued::class,
            InvoicePaymentPending::class,
            InvoicePaid::class,
            InvoiceFailed::class,
        ]);

        $payer = User::factory()->create();
        $service = app(InvoiceService::class);
        $invoice = $service->createDraftInvoice($payer, [
            ['description' => 'Plan', 'quantity' => 1, 'unit_amount' => 1000],
        ], ['currency' => 'USD']);
        $issued = $service->issueInvoice($invoice, $payer);
        $payment = Payment::factory()->create([
            'user_id' => $payer->id,
            'payer_user_id' => $payer->id,
            'subscription_id' => null,
            'amount' => 1000,
            'currency' => 'USD',
        ]);
        $pending = $service->markPaymentPending($issued, $payment);
        $service->markPaid($pending, $payment->refresh());

        $failedInvoice = $service->issueInvoice($service->createDraftInvoice($payer, [
            ['description' => 'Retry Plan', 'quantity' => 1, 'unit_amount' => 1500],
        ], ['currency' => 'USD']), $payer);
        $failedPayment = Payment::factory()->create([
            'user_id' => $payer->id,
            'payer_user_id' => $payer->id,
            'subscription_id' => null,
            'amount' => 1500,
            'currency' => 'USD',
        ]);
        $service->markFailed($service->markPaymentPending($failedInvoice, $failedPayment), $failedPayment, 'card_declined');

        Event::assertDispatchedTimes(InvoiceIssued::class, 2);
        Event::assertDispatchedTimes(InvoicePaymentPending::class, 2);
        Event::assertDispatchedTimes(InvoicePaid::class, 1);
        Event::assertDispatchedTimes(InvoiceFailed::class, 1);
    }

    public function test_invoice_payment_replay_does_not_duplicate_payment_pending_event(): void
    {
        Event::fake([InvoicePaymentPending::class]);
        $payer = User::factory()->create();
        $service = app(InvoiceService::class);
        $invoice = $service->issueInvoice($service->createDraftInvoice($payer, [
            ['description' => 'Plan', 'quantity' => 1, 'unit_amount' => 1000],
        ], ['currency' => 'USD']), $payer);
        $payment = Payment::factory()->create([
            'user_id' => $payer->id,
            'payer_user_id' => $payer->id,
            'subscription_id' => null,
            'amount' => 1000,
            'currency' => 'USD',
        ]);

        $service->markPaymentPending($invoice, $payment);
        $service->markPaymentPending($invoice->refresh(), $payment->refresh());

        Event::assertDispatchedTimes(InvoicePaymentPending::class, 1);
    }
}
