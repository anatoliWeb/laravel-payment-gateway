<?php

namespace Database\Seeders\Billing;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class BillingDemoReportDataSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $now = now();
        $customerOne = $this->demoUser(self::CUSTOMER_ONE_EMAIL);
        $customerTwo = $this->demoUser(self::CUSTOMER_TWO_EMAIL);
        $customerThree = $this->demoUser(self::CUSTOMER_THREE_EMAIL);
        $company = $this->demoCompany();
        $seller = $this->demoSeller();
        $proPlan = $this->demoPlan('pro');
        $enterprisePlan = $this->demoPlan('enterprise');
        $wallet = $customerOne->wallet()->firstOrFail();
        $usdCurrencyId = $this->demoCurrencyId('USD');
        $eurCurrencyId = $this->demoCurrencyId('EUR');
        $usdBalance = $wallet->balances()->where('currency_id', $usdCurrencyId)->firstOrFail();
        $eurBalance = $wallet->balances()->where('currency_id', $eurCurrencyId)->firstOrFail();

        $reportSubscriptionOne = $this->upsertSubscription([
            'uuid' => 'demo-subscription-report-01',
            'user_id' => $customerOne->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'started_at' => $now->copy()->subMonths(2),
            'current_period_start' => $now->copy()->subMonths(2)->startOfMonth(),
            'current_period_end' => $now->copy()->subMonth()->endOfMonth(),
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'cancel_at_period_end' => false,
            'ended_at' => null,
            'metadata' => [
                'purpose' => 'report_history_subscription_01',
            ],
        ]);
        $this->upsertSubscription([
            'uuid' => 'demo-subscription-report-02',
            'user_id' => $customerTwo->id,
            'plan_id' => $enterprisePlan->id,
            'status' => 'past_due',
            'started_at' => $now->copy()->subMonths(3),
            'current_period_start' => $now->copy()->subMonths(2)->startOfMonth(),
            'current_period_end' => $now->copy()->subMonth()->endOfMonth(),
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'cancel_at_period_end' => false,
            'ended_at' => null,
            'metadata' => [
                'purpose' => 'report_history_subscription_02',
            ],
        ]);

        $reportPaymentOne = $this->upsertPayment([
            'uuid' => 'demo-payment-report-01',
            'user_id' => $customerOne->id,
            'payer_user_id' => $customerOne->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'provider_account_id' => null,
            'subscription_id' => $reportSubscriptionOne->id,
            'invoice_id' => null,
            'parent_payment_id' => null,
            'amount' => 14900,
            'currency' => 'USD',
            'status' => 'succeeded',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-report-01',
            'description' => 'Report seed payment one',
            'failure_reason' => null,
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'purpose' => 'report_data',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
                'company_id' => $company->id,
                'seller_id' => $seller->id,
            ],
            'paid_at' => $now->copy()->subMonths(2)->addDays(4),
            'failed_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
        ]);

        $reportInvoiceOne = $this->upsertInvoice([
            'uuid' => 'demo-invoice-report-01',
            'number' => 'INV-DEMO-RPT-0001',
            'user_id' => $customerOne->id,
            'payer_user_id' => $customerOne->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'subscription_id' => $reportSubscriptionOne->id,
            'payment_id' => $reportPaymentOne->id,
            'status' => 'paid',
            'currency' => 'USD',
            'subtotal_amount' => 14900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 14900,
            'paid_amount' => 14900,
            'due_amount' => 0,
            'issued_at' => $now->copy()->subMonths(2)->startOfMonth(),
            'due_at' => $now->copy()->subMonths(2)->addDays(14),
            'paid_at' => $now->copy()->subMonths(2)->addDays(4),
            'voided_at' => null,
            'overdue_at' => null,
            'description' => 'Report seed paid invoice',
            'metadata' => [
                'purpose' => 'report_data',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
            ],
        ]);
        $this->upsertInvoiceItem($reportInvoiceOne->id, 'subscription', 'Pro plan history seed', 1, 14900);

        $reportPaymentTwo = $this->upsertPayment([
            'uuid' => 'demo-payment-report-02',
            'user_id' => $customerTwo->id,
            'payer_user_id' => $customerTwo->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'provider_account_id' => null,
            'subscription_id' => $customerTwo->subscriptions()->where('uuid', 'demo-subscription-trialing')->firstOrFail()->id,
            'invoice_id' => null,
            'parent_payment_id' => null,
            'amount' => 20900,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-report-02',
            'description' => 'Report seed payment two',
            'failure_reason' => null,
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'purpose' => 'report_data',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
                'company_id' => $company->id,
                'seller_id' => $seller->id,
            ],
            'paid_at' => $now->copy()->subWeeks(2),
            'failed_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
        ]);

        $reportInvoiceTwo = $this->upsertInvoice([
            'uuid' => 'demo-invoice-report-02',
            'number' => 'INV-DEMO-RPT-0002',
            'user_id' => $customerTwo->id,
            'payer_user_id' => $customerTwo->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'subscription_id' => $customerTwo->subscriptions()->where('uuid', 'demo-subscription-trialing')->firstOrFail()->id,
            'payment_id' => $reportPaymentTwo->id,
            'status' => 'paid',
            'currency' => 'EUR',
            'subtotal_amount' => 20900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 20900,
            'paid_amount' => 20900,
            'due_amount' => 0,
            'issued_at' => $now->copy()->subWeeks(3),
            'due_at' => $now->copy()->subWeeks(2),
            'paid_at' => $now->copy()->subWeeks(2),
            'voided_at' => null,
            'overdue_at' => null,
            'description' => 'Report seed paid invoice two',
            'metadata' => [
                'purpose' => 'report_data',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
            ],
        ]);
        $this->upsertInvoiceItem($reportInvoiceTwo->id, 'subscription', 'Enterprise history seed', 1, 20900);

        $reportFailedPayment = $this->upsertPayment([
            'uuid' => 'demo-payment-report-failed',
            'user_id' => $customerThree->id,
            'payer_user_id' => $customerThree->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'provider_account_id' => null,
            'subscription_id' => $customerThree->subscriptions()->where('status', 'past_due')->firstOrFail()->id,
            'invoice_id' => null,
            'parent_payment_id' => null,
            'amount' => 8100,
            'currency' => 'USD',
            'status' => 'failed',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-report-failed',
            'description' => 'Report seed failed payment',
            'failure_reason' => 'card_declined',
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'purpose' => 'report_data',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
                'company_id' => $company->id,
                'seller_id' => $seller->id,
            ],
            'paid_at' => null,
            'failed_at' => $now->copy()->subDays(11),
            'expired_at' => null,
            'cancelled_at' => null,
        ]);

        $this->upsertWalletTransaction([
            'wallet_id' => $wallet->id,
            'wallet_balance_id' => $usdBalance->id,
            'currency_id' => $usdCurrencyId,
            'payment_id' => $reportPaymentOne->id,
            'subscription_id' => $reportSubscriptionOne->id,
            'type' => 'debit',
            'direction' => 'debit',
            'amount' => 14900,
            'balance_available_before' => 25000,
            'balance_available_after' => 10100,
            'balance_held_before' => 2000,
            'balance_held_after' => 2000,
            'idempotency_key' => 'demo-report-wallet-debit-usd',
            'reference_type' => Payment::class,
            'reference_id' => $reportPaymentOne->id,
            'reason' => 'report_wallet_debit',
            'status' => 'completed',
            'metadata' => [
                'purpose' => 'report_data',
            ],
        ]);

        $this->upsertWalletTransaction([
            'wallet_id' => $wallet->id,
            'wallet_balance_id' => $eurBalance->id,
            'currency_id' => $eurCurrencyId,
            'payment_id' => $reportPaymentTwo->id,
            'subscription_id' => $customerTwo->subscriptions()->where('uuid', 'demo-subscription-trialing')->firstOrFail()->id,
            'type' => 'top_up',
            'direction' => 'credit',
            'amount' => 20900,
            'balance_available_before' => 8000,
            'balance_available_after' => 28900,
            'balance_held_before' => 0,
            'balance_held_after' => 0,
            'idempotency_key' => 'demo-report-wallet-top-up-eur',
            'reference_type' => Payment::class,
            'reference_id' => $reportPaymentTwo->id,
            'reason' => 'report_wallet_top_up',
            'status' => 'completed',
            'metadata' => [
                'purpose' => 'report_data',
            ],
        ]);

        DB::table('subscriptions')->where('id', $reportSubscriptionOne->id)->update([
            'created_at' => $now->copy()->subMonths(2),
            'updated_at' => $now->copy()->subMonths(2),
        ]);
        DB::table('payments')->where('id', $reportPaymentOne->id)->update([
            'created_at' => $now->copy()->subMonths(2)->addDays(4),
            'updated_at' => $now->copy()->subMonths(2)->addDays(4),
        ]);
        DB::table('payments')->where('id', $reportPaymentTwo->id)->update([
            'created_at' => $now->copy()->subWeeks(2),
            'updated_at' => $now->copy()->subWeeks(2),
        ]);
        DB::table('payments')->where('id', $reportFailedPayment->id)->update([
            'created_at' => $now->copy()->subDays(11),
            'updated_at' => $now->copy()->subDays(11),
        ]);
        DB::table('invoices')->where('id', $reportInvoiceOne->id)->update([
            'created_at' => $now->copy()->subMonths(2)->startOfMonth(),
            'updated_at' => $now->copy()->subMonths(2)->addDays(4),
        ]);
        DB::table('invoices')->where('id', $reportInvoiceTwo->id)->update([
            'created_at' => $now->copy()->subWeeks(3),
            'updated_at' => $now->copy()->subWeeks(2),
        ]);
        DB::table('wallet_transactions')->whereIn('id', [
            $wallet->transactions()->where('idempotency_key', 'demo-report-wallet-debit-usd')->firstOrFail()->id,
            $wallet->transactions()->where('idempotency_key', 'demo-report-wallet-top-up-eur')->firstOrFail()->id,
        ])->update([
            'created_at' => $now->copy()->subWeeks(2),
            'updated_at' => $now->copy()->subWeeks(2),
        ]);
    }
}
