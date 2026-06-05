<?php

namespace Tests\Feature\Billing;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_creates_draft_invoice_with_items_totals_and_ownership_scope(): void
    {
        $payer = User::factory()->create();
        $seller = Seller::factory()->create();

        $invoice = app(InvoiceService::class)->createDraftInvoice($payer, [
            ['description' => 'Plan', 'quantity' => 2, 'unit_amount' => 1000, 'discount_amount' => 200, 'tax_amount' => 100],
            ['description' => 'Usage', 'quantity' => 1, 'unit_amount' => 500],
        ], [
            'currency' => 'USD',
            'seller_id' => $seller->id,
            'idempotency_key' => 'invoice-create-1',
        ]);

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame(2500, $invoice->subtotal_amount);
        $this->assertSame(200, $invoice->discount_amount);
        $this->assertSame(100, $invoice->tax_amount);
        $this->assertSame(2400, $invoice->total_amount);
        $this->assertSame(2400, $invoice->due_amount);
        $this->assertSame($seller->company_id, $invoice->company_id);
        $this->assertSame($seller->id, $invoice->seller_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'billing.invoice_created']);
    }

    public function test_it_replays_invoice_creation_with_same_idempotency_key(): void
    {
        $payer = User::factory()->create();
        $service = app(InvoiceService::class);
        $items = [['description' => 'Plan', 'quantity' => 1, 'unit_amount' => 1000]];
        $context = ['currency' => 'USD', 'idempotency_key' => 'invoice-create-replay-1'];

        $first = $service->createDraftInvoice($payer, $items, $context);
        $second = $service->createDraftInvoice($payer, $items, $context);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::query()->where('payer_user_id', $payer->id)->count());
    }

    public function test_it_issues_and_voids_invoice_with_valid_transitions(): void
    {
        $payer = User::factory()->create();
        $actor = User::factory()->create();
        $service = app(InvoiceService::class);
        $invoice = $service->createDraftInvoice($payer, [
            ['description' => 'Plan', 'quantity' => 1, 'unit_amount' => 1000],
        ], ['currency' => 'USD']);

        $issued = $service->issueInvoice($invoice, $actor);
        $void = $service->voidInvoice($issued, $actor, 'Customer cancelled before payment');

        $this->assertSame(Invoice::STATUS_ISSUED, $issued->status);
        $this->assertNotNull($issued->number);
        $this->assertSame(Invoice::STATUS_VOID, $void->status);
        $this->assertNotNull($void->voided_at);
    }

    public function test_it_rejects_empty_issue_and_invalid_final_transition(): void
    {
        $payer = User::factory()->create();
        $actor = User::factory()->create();
        $empty = Invoice::factory()->create(['payer_user_id' => $payer->id, 'total_amount' => 0, 'due_amount' => 0]);
        $service = app(InvoiceService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invoice_cannot_issue_empty');
        $service->issueInvoice($empty, $actor);
    }

    public function test_it_marks_payment_pending_and_paid(): void
    {
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

        $pending = $service->markPaymentPending($invoice, $payment);
        $paid = $service->markPaid($pending, $payment->refresh());

        $this->assertSame(Invoice::STATUS_PAYMENT_PENDING, $pending->status);
        $this->assertSame($invoice->id, $payment->refresh()->invoice_id);
        $this->assertSame(Invoice::STATUS_PAID, $paid->status);
        $this->assertSame(0, $paid->due_amount);
        $this->assertGreaterThan(0, ActivityLog::query()->whereIn('action', [
            'billing.invoice_payment_pending',
            'billing.invoice_paid',
        ])->count());
    }

    public function test_it_rejects_conflicting_seller_company_scope(): void
    {
        $payer = User::factory()->create();
        $seller = Seller::factory()->create();
        $otherCompany = Company::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payment_ownership_scope_conflict');

        app(InvoiceService::class)->createDraftInvoice($payer, [
            ['description' => 'Plan', 'quantity' => 1, 'unit_amount' => 1000],
        ], [
            'currency' => 'USD',
            'seller_id' => $seller->id,
            'company_id' => $otherCompany->id,
        ]);
    }
}
