<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InvoicePaymentFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_creates_payment_for_invoice_due_amount_without_activating_subscription(): void
    {
        $payer = User::factory()->create();
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $payer->id]);
        $subscription = Subscription::factory()->pending()->create(['user_id' => $payer->id]);
        $service = app(InvoiceService::class);
        $invoice = $service->issueInvoice($service->createDraftInvoice($payer, [
            ['description' => 'Subscription plan', 'quantity' => 1, 'unit_amount' => 2900],
        ], [
            'currency' => 'USD',
            'subscription_id' => $subscription->id,
        ]), $payer);

        $payment = $service->createPaymentForInvoice($invoice, $payer, [
            'payment_source' => 'payment_method',
            'idempotency_key' => 'invoice-payment-1',
        ]);

        $this->assertSame(2900, $payment->amount);
        $this->assertSame('USD', $payment->currency);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame(Invoice::STATUS_PAYMENT_PENDING, $invoice->refresh()->status);
        $this->assertSame($payment->id, $invoice->payment_id);
        $this->assertSame('pending', $subscription->refresh()->status);
    }

    public function test_invoice_payment_requires_matching_currency(): void
    {
        $payer = User::factory()->create();
        $service = app(InvoiceService::class);
        $invoice = $service->issueInvoice($service->createDraftInvoice($payer, [
            ['description' => 'Plan', 'quantity' => 1, 'unit_amount' => 1000],
        ], ['currency' => 'USD']), $payer);

        $this->expectExceptionMessage('invoice_payment_currency_mismatch');

        $service->createPaymentForInvoice($invoice, $payer, [
            'currency' => 'EUR',
            'idempotency_key' => 'invoice-payment-currency-conflict',
        ]);
    }

    public function test_invoice_payment_does_not_mark_invoice_paid_until_explicit_paid_transition(): void
    {
        $payer = User::factory()->create();
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $payer->id]);
        $service = app(InvoiceService::class);
        $invoice = $service->issueInvoice($service->createDraftInvoice($payer, [
            ['description' => 'Plan', 'quantity' => 1, 'unit_amount' => 1000],
        ], ['currency' => 'USD']), $payer);

        $payment = $service->createPaymentForInvoice($invoice, $payer, [
            'payment_source' => 'payment_method',
            'idempotency_key' => 'invoice-payment-not-paid-yet',
        ]);

        $this->assertNotSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
        $this->assertSame(1, Payment::query()->whereKey($payment->id)->where('invoice_id', $invoice->id)->count());
    }

    public function test_invoice_payment_replays_same_payment_for_same_idempotency_key(): void
    {
        $payer = User::factory()->create();
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $payer->id]);
        $service = app(InvoiceService::class);
        $invoice = $service->issueInvoice($service->createDraftInvoice($payer, [
            ['description' => 'Plan', 'quantity' => 1, 'unit_amount' => 1000],
        ], ['currency' => 'USD']), $payer);

        $first = $service->createPaymentForInvoice($invoice, $payer, [
            'payment_source' => 'payment_method',
            'idempotency_key' => 'invoice-payment-replay-1',
        ]);
        $second = $service->createPaymentForInvoice($invoice->refresh(), $payer, [
            'payment_source' => 'payment_method',
            'idempotency_key' => 'invoice-payment-replay-1',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::query()->where('invoice_id', $invoice->id)->count());
        $this->assertSame(Invoice::STATUS_PAYMENT_PENDING, $invoice->refresh()->status);
    }
}
