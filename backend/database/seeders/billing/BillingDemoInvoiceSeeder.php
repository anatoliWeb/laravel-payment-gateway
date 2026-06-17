<?php

namespace Database\Seeders\Billing;

use App\Models\Invoice;

class BillingDemoInvoiceSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $now = now();
        $customerOne = $this->demoUser(self::CUSTOMER_ONE_EMAIL);
        $customerTwo = $this->demoUser(self::CUSTOMER_TWO_EMAIL);
        $customerThree = $this->demoUser(self::CUSTOMER_THREE_EMAIL);
        $normal = $this->demoUser(self::NORMAL_EMAIL);

        $company = $this->demoCompany();
        $seller = $this->demoSeller();
        $activeSubscription = $this->demoPlan('pro')->subscriptions()->where('user_id', $customerOne->id)->firstOrFail();
        $trialingSubscription = $this->demoPlan('basic')->subscriptions()->where('user_id', $customerTwo->id)->firstOrFail();
        $pastDueSubscription = $this->demoPlan('enterprise')->subscriptions()->where('user_id', $customerThree->id)->firstOrFail();
        $cancelledSubscription = $this->demoPlan('demo_enterprise')->subscriptions()->where('user_id', $normal->id)->firstOrFail();

        $paidInvoice = $this->upsertInvoice([
            'uuid' => 'demo-invoice-paid',
            'number' => 'INV-DEMO-0001',
            'user_id' => $customerOne->id,
            'payer_user_id' => $customerOne->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'subscription_id' => $activeSubscription->id,
            'payment_id' => null,
            'status' => Invoice::STATUS_PAID,
            'currency' => 'USD',
            'subtotal_amount' => 12900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 12900,
            'paid_amount' => 12900,
            'due_amount' => 0,
            'issued_at' => $now->copy()->subDays(12),
            'due_at' => $now->copy()->subDays(2),
            'paid_at' => $now->copy()->subDays(2),
            'voided_at' => null,
            'overdue_at' => null,
            'description' => 'Paid demo invoice',
            'metadata' => [
                'purpose' => 'paid_invoice_demo',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
            ],
        ]);
        $this->upsertInvoiceItem($paidInvoice->id, 'subscription', 'Pro monthly plan', 1, 12900);

        $pendingInvoice = $this->upsertInvoice([
            'uuid' => 'demo-invoice-payment-pending',
            'number' => 'INV-DEMO-0002',
            'user_id' => $customerTwo->id,
            'payer_user_id' => $customerTwo->id,
            'company_id' => null,
            'seller_id' => null,
            'subscription_id' => $trialingSubscription->id,
            'payment_id' => null,
            'status' => Invoice::STATUS_PAYMENT_PENDING,
            'currency' => 'EUR',
            'subtotal_amount' => 2900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 2900,
            'paid_amount' => 0,
            'due_amount' => 2900,
            'issued_at' => $now->copy()->subDays(5),
            'due_at' => $now->copy()->addDays(10),
            'paid_at' => null,
            'voided_at' => null,
            'overdue_at' => null,
            'description' => 'Pending demo invoice',
            'metadata' => [
                'purpose' => 'pending_invoice_demo',
            ],
            'ownership_metadata' => [
                'scope' => 'user',
            ],
        ]);
        $this->upsertInvoiceItem($pendingInvoice->id, 'subscription', 'Basic monthly plan', 1, 2900);

        $overdueInvoice = $this->upsertInvoice([
            'uuid' => 'demo-invoice-overdue',
            'number' => 'INV-DEMO-0003',
            'user_id' => $customerThree->id,
            'payer_user_id' => $customerThree->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'subscription_id' => $pastDueSubscription->id,
            'payment_id' => null,
            'status' => Invoice::STATUS_OVERDUE,
            'currency' => 'USD',
            'subtotal_amount' => 49900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 49900,
            'paid_amount' => 0,
            'due_amount' => 49900,
            'issued_at' => $now->copy()->subMonths(2),
            'due_at' => $now->copy()->subMonth(),
            'paid_at' => null,
            'voided_at' => null,
            'overdue_at' => $now->copy()->subWeeks(2),
            'description' => 'Overdue demo invoice',
            'metadata' => [
                'purpose' => 'overdue_invoice_demo',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
            ],
        ]);
        $this->upsertInvoiceItem($overdueInvoice->id, 'subscription', 'Enterprise monthly plan', 1, 49900);

        $failedInvoice = $this->upsertInvoice([
            'uuid' => 'demo-invoice-failed',
            'number' => 'INV-DEMO-0004',
            'user_id' => $normal->id,
            'payer_user_id' => $normal->id,
            'company_id' => null,
            'seller_id' => null,
            'subscription_id' => $cancelledSubscription->id,
            'payment_id' => null,
            'status' => Invoice::STATUS_FAILED,
            'currency' => 'USD',
            'subtotal_amount' => 9900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 9900,
            'paid_amount' => 0,
            'due_amount' => 9900,
            'issued_at' => $now->copy()->subWeeks(3),
            'due_at' => $now->copy()->subWeeks(2),
            'paid_at' => null,
            'voided_at' => null,
            'overdue_at' => null,
            'description' => 'Failed demo invoice',
            'metadata' => [
                'purpose' => 'failed_invoice_demo',
            ],
            'ownership_metadata' => [
                'scope' => 'user',
            ],
        ]);
        $this->upsertInvoiceItem($failedInvoice->id, 'subscription', 'Demo enterprise monthly plan', 1, 9900);
    }
}
