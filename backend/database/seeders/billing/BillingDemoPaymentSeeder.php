<?php

namespace Database\Seeders\Billing;

use App\Models\Payment;

class BillingDemoPaymentSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $now = now();
        $company = $this->demoCompany();
        $seller = $this->demoSeller();
        $customerOne = $this->demoUser(self::CUSTOMER_ONE_EMAIL);
        $customerTwo = $this->demoUser(self::CUSTOMER_TWO_EMAIL);
        $customerThree = $this->demoUser(self::CUSTOMER_THREE_EMAIL);
        $normal = $this->demoUser(self::NORMAL_EMAIL);
        $providerAccount = $this->upsertProviderAccount([
            'uuid' => 'demo-platform-simulator-account',
            'user_id' => $this->demoUser(self::ADMIN_EMAIL)->id,
            'company_id' => $company->id,
            'seller_id' => null,
            'provider' => 'simulator',
            'display_name' => 'Demo Platform Simulator',
            'status' => 'active',
            'mode' => 'test',
            'config_source' => 'database',
            'public_config' => [
                'seeded' => true,
                'demo' => true,
            ],
            'capabilities' => [
                'charge' => true,
                'refund' => true,
                'webhook_verification' => true,
            ],
            'metadata' => [
                'purpose' => 'platform_simulator_account',
            ],
            'credentials' => [
                'api_key' => 'fake_platform_simulator_key_0000',
            ],
        ]);

        $activeSubscription = $this->demoPlan('pro')->subscriptions()->where('user_id', $customerOne->id)->firstOrFail();
        $trialingSubscription = $this->demoPlan('basic')->subscriptions()->where('user_id', $customerTwo->id)->firstOrFail();
        $pastDueSubscription = $this->demoPlan('enterprise')->subscriptions()->where('user_id', $customerThree->id)->firstOrFail();
        $cancelledSubscription = $this->demoPlan('demo_enterprise')->subscriptions()->where('user_id', $normal->id)->firstOrFail();
        $usdInvoice = $this->demoPlan('pro')->subscriptions()->where('user_id', $customerOne->id)->firstOrFail()->invoices()->where('number', 'INV-DEMO-0001')->firstOrFail();
        $pendingInvoice = $this->demoPlan('basic')->subscriptions()->where('user_id', $customerTwo->id)->firstOrFail()->invoices()->where('number', 'INV-DEMO-0002')->firstOrFail();
        $overdueInvoice = $this->demoPlan('enterprise')->subscriptions()->where('user_id', $customerThree->id)->firstOrFail()->invoices()->where('number', 'INV-DEMO-0003')->firstOrFail();
        $failedInvoice = $this->demoPlan('demo_enterprise')->subscriptions()->where('user_id', $normal->id)->firstOrFail()->invoices()->where('number', 'INV-DEMO-0004')->firstOrFail();

        $succeeded = $this->upsertPayment([
            'uuid' => 'demo-payment-succeeded',
            'user_id' => $customerOne->id,
            'payer_user_id' => $customerOne->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'provider_account_id' => $providerAccount->id,
            'subscription_id' => $activeSubscription->id,
            'invoice_id' => $usdInvoice->id,
            'parent_payment_id' => null,
            'amount' => 12900,
            'currency' => 'USD',
            'status' => 'succeeded',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-prov-succeeded',
            'description' => 'Demo succeeded payment',
            'failure_reason' => null,
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'purpose' => 'payment_success_demo',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
                'company_id' => $company->id,
                'seller_id' => $seller->id,
            ],
            'paid_at' => $now->copy()->subDays(2),
            'failed_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
        ]);

        $pending = $this->upsertPayment([
            'uuid' => 'demo-payment-pending',
            'user_id' => $customerTwo->id,
            'payer_user_id' => $customerTwo->id,
            'company_id' => null,
            'seller_id' => null,
            'provider_account_id' => $providerAccount->id,
            'subscription_id' => $trialingSubscription->id,
            'invoice_id' => $pendingInvoice->id,
            'parent_payment_id' => null,
            'amount' => 2900,
            'currency' => 'EUR',
            'status' => 'pending',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-prov-pending',
            'description' => 'Demo pending payment',
            'failure_reason' => null,
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'purpose' => 'payment_pending_demo',
            ],
            'ownership_metadata' => [
                'scope' => 'user',
            ],
            'paid_at' => null,
            'failed_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
        ]);

        $processing = $this->upsertPayment([
            'uuid' => 'demo-payment-processing',
            'user_id' => $customerTwo->id,
            'payer_user_id' => $customerTwo->id,
            'company_id' => null,
            'seller_id' => null,
            'provider_account_id' => $providerAccount->id,
            'subscription_id' => $trialingSubscription->id,
            'invoice_id' => null,
            'parent_payment_id' => null,
            'amount' => 1500,
            'currency' => 'USD',
            'status' => 'processing',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-prov-processing',
            'description' => 'Demo processing payment',
            'failure_reason' => null,
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'purpose' => 'payment_processing_demo',
            ],
            'ownership_metadata' => [
                'scope' => 'user',
            ],
            'paid_at' => null,
            'failed_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
        ]);

        $failed = $this->upsertPayment([
            'uuid' => 'demo-payment-failed',
            'user_id' => $customerThree->id,
            'payer_user_id' => $customerThree->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'provider_account_id' => $providerAccount->id,
            'subscription_id' => $pastDueSubscription->id,
            'invoice_id' => $overdueInvoice->id,
            'parent_payment_id' => null,
            'amount' => 49900,
            'currency' => 'USD',
            'status' => 'failed',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-prov-failed',
            'description' => 'Demo failed payment',
            'failure_reason' => 'card_declined',
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'purpose' => 'payment_failed_demo',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
                'company_id' => $company->id,
                'seller_id' => $seller->id,
            ],
            'paid_at' => null,
            'failed_at' => $now->copy()->subHours(2),
            'expired_at' => null,
            'cancelled_at' => null,
        ]);

        $expired = $this->upsertPayment([
            'uuid' => 'demo-payment-expired',
            'user_id' => $normal->id,
            'payer_user_id' => $normal->id,
            'company_id' => null,
            'seller_id' => null,
            'provider_account_id' => $providerAccount->id,
            'subscription_id' => $cancelledSubscription->id,
            'invoice_id' => $failedInvoice->id,
            'parent_payment_id' => null,
            'amount' => 9900,
            'currency' => 'USD',
            'status' => 'expired',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-prov-expired',
            'description' => 'Demo expired payment',
            'failure_reason' => 'payment_expired',
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'purpose' => 'payment_expired_demo',
            ],
            'ownership_metadata' => [
                'scope' => 'user',
            ],
            'paid_at' => null,
            'failed_at' => null,
            'expired_at' => $now->copy()->subHour(),
            'cancelled_at' => null,
        ]);

        $cancelled = $this->upsertPayment([
            'uuid' => 'demo-payment-cancelled',
            'user_id' => $normal->id,
            'payer_user_id' => $normal->id,
            'company_id' => null,
            'seller_id' => null,
            'provider_account_id' => $providerAccount->id,
            'subscription_id' => $cancelledSubscription->id,
            'invoice_id' => null,
            'parent_payment_id' => null,
            'amount' => 7900,
            'currency' => 'EUR',
            'status' => 'cancelled',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-prov-cancelled',
            'description' => 'Demo cancelled payment',
            'failure_reason' => 'cancelled_by_user',
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'purpose' => 'payment_cancelled_demo',
            ],
            'ownership_metadata' => [
                'scope' => 'user',
            ],
            'paid_at' => null,
            'failed_at' => null,
            'expired_at' => null,
            'cancelled_at' => $now->copy()->subMinutes(40),
        ]);

        $this->upsertPaymentTransaction([
            'payment_id' => $succeeded->id,
            'type' => 'payment_created',
            'status_from' => null,
            'status_to' => 'pending',
            'amount' => 12900,
            'currency' => 'USD',
            'message' => 'Demo payment created.',
            'payload' => [
                'source' => 'billing_demo_seeder',
                'purpose' => 'payment_history',
            ],
        ]);
        $this->upsertPaymentTransaction([
            'payment_id' => $succeeded->id,
            'type' => 'payment_succeeded',
            'status_from' => 'processing',
            'status_to' => 'succeeded',
            'amount' => 12900,
            'currency' => 'USD',
            'message' => 'Demo payment succeeded.',
            'payload' => [
                'source' => 'billing_demo_seeder',
                'purpose' => 'payment_history',
            ],
        ]);
        $this->upsertPaymentTransaction([
            'payment_id' => $failed->id,
            'type' => 'payment_failed',
            'status_from' => 'processing',
            'status_to' => 'failed',
            'amount' => 49900,
            'currency' => 'USD',
            'message' => 'Demo payment failed.',
            'payload' => [
                'source' => 'billing_demo_seeder',
                'purpose' => 'payment_history',
            ],
        ]);
        $this->upsertPaymentTransaction([
            'payment_id' => $expired->id,
            'type' => 'payment_expired',
            'status_from' => 'processing',
            'status_to' => 'expired',
            'amount' => 9900,
            'currency' => 'USD',
            'message' => 'Demo payment expired.',
            'payload' => [
                'source' => 'billing_demo_seeder',
                'purpose' => 'payment_history',
            ],
        ]);
        $this->upsertPaymentTransaction([
            'payment_id' => $cancelled->id,
            'type' => 'payment_cancelled',
            'status_from' => 'pending',
            'status_to' => 'cancelled',
            'amount' => 7900,
            'currency' => 'EUR',
            'message' => 'Demo payment cancelled.',
            'payload' => [
                'source' => 'billing_demo_seeder',
                'purpose' => 'payment_history',
            ],
        ]);

        $usdInvoice->forceFill(['payment_id' => $succeeded->id])->save();
        $pendingInvoice->forceFill(['payment_id' => $pending->id])->save();
        $overdueInvoice->forceFill(['payment_id' => $expired->id])->save();
        $failedInvoice->forceFill(['payment_id' => $failed->id])->save();

        $wallet = $this->upsertWallet($customerOne->id, 'demo-wallet-customer', 'active');
        $usdCurrencyId = $this->demoCurrencyId('USD');
        $usdBalance = $this->upsertWalletBalance($wallet->id, $usdCurrencyId, 25000, 2000);

        $this->upsertWalletTransaction([
            'wallet_id' => $wallet->id,
            'wallet_balance_id' => $usdBalance->id,
            'currency_id' => $usdCurrencyId,
            'payment_id' => $succeeded->id,
            'subscription_id' => $activeSubscription->id,
            'type' => 'debit',
            'direction' => 'debit',
            'amount' => 12900,
            'balance_available_before' => 25000,
            'balance_available_after' => 12100,
            'balance_held_before' => 2000,
            'balance_held_after' => 2000,
            'idempotency_key' => 'demo-wallet-debit-payment-succeeded',
            'reference_type' => Payment::class,
            'reference_id' => $succeeded->id,
            'reason' => 'wallet_payment_debit',
            'status' => 'completed',
            'metadata' => [
                'purpose' => 'payment_wallet_debit_demo',
            ],
        ]);
        $usdBalance->forceFill([
            'available_amount' => 12100,
            'held_amount' => 2000,
        ])->save();

        $this->upsertIdempotencyKey($customerOne->id, 'payment.create', 'POST', '/api/v1/billing/payments', 'completed', 201, $succeeded);
        $this->upsertIdempotencyKey($customerTwo->id, 'payment.create', 'POST', '/api/v1/billing/payments', 'processing', 202, $pending);
        $this->upsertIdempotencyKey($customerThree->id, 'payment.create', 'POST', '/api/v1/billing/payments', 'failed', 422, $failed);
        $this->upsertIdempotencyKey($normal->id, 'wallet.adjustment', 'POST', '/api/v1/billing/wallet-adjustments', 'processing', null, null);
    }
}
