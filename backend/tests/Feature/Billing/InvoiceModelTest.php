<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invoice_has_items_and_belongs_to_payer(): void
    {
        $payer = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $payer->id, 'payer_user_id' => $payer->id]);
        InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id]);

        $this->assertCount(2, $invoice->items);
        $this->assertTrue($invoice->payer->is($payer));
    }

    public function test_invoice_belongs_to_company_seller_payment_and_subscription(): void
    {
        $seller = Seller::factory()->create();
        $subscription = Subscription::factory()->create();
        $payment = Payment::factory()->create(['subscription_id' => $subscription->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $seller->company_id,
            'seller_id' => $seller->id,
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
        ]);

        $this->assertInstanceOf(Company::class, $invoice->company);
        $this->assertTrue($invoice->seller->is($seller));
        $this->assertTrue($invoice->subscription->is($subscription));
        $this->assertTrue($invoice->payment->is($payment));
    }
}
