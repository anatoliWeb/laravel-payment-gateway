<?php

namespace Database\Seeders\Billing;

class BillingDemoWalletSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $customerOne = $this->demoUser(self::CUSTOMER_ONE_EMAIL);
        $customerTwo = $this->demoUser(self::CUSTOMER_TWO_EMAIL);
        $wallet = $this->upsertWallet($customerOne->id, 'demo-wallet-customer', 'active');

        $usdCurrencyId = $this->demoCurrencyId('USD');
        $eurCurrencyId = $this->demoCurrencyId('EUR');

        $usdBalance = $this->upsertWalletBalance($wallet->id, $usdCurrencyId, 25000, 2000);
        $eurBalance = $this->upsertWalletBalance($wallet->id, $eurCurrencyId, 8000, 0);

        $this->upsertWalletTransaction([
            'wallet_id' => $wallet->id,
            'wallet_balance_id' => $usdBalance->id,
            'currency_id' => $usdCurrencyId,
            'payment_id' => null,
            'subscription_id' => null,
            'type' => 'top_up',
            'direction' => 'credit',
            'amount' => 25000,
            'balance_available_before' => 0,
            'balance_available_after' => 25000,
            'balance_held_before' => 0,
            'balance_held_after' => 0,
            'idempotency_key' => 'demo-wallet-top-up-usd',
            'reason' => 'demo_seed_top_up',
            'status' => 'completed',
            'metadata' => [
                'purpose' => 'wallet_top_up_demo',
            ],
        ]);

        $this->upsertWalletTransaction([
            'wallet_id' => $wallet->id,
            'wallet_balance_id' => $usdBalance->id,
            'currency_id' => $usdCurrencyId,
            'payment_id' => null,
            'subscription_id' => null,
            'type' => 'adjustment',
            'direction' => 'neutral',
            'amount' => 1000,
            'balance_available_before' => 25000,
            'balance_available_after' => 26000,
            'balance_held_before' => 0,
            'balance_held_after' => 0,
            'idempotency_key' => 'demo-wallet-adjustment-usd',
            'reason' => 'manual_demo_adjustment',
            'status' => 'completed',
            'metadata' => [
                'purpose' => 'wallet_adjustment_demo',
            ],
        ]);

        $this->upsertWalletTransaction([
            'wallet_id' => $wallet->id,
            'wallet_balance_id' => $eurBalance->id,
            'currency_id' => $eurCurrencyId,
            'payment_id' => null,
            'subscription_id' => null,
            'type' => 'hold',
            'direction' => 'debit',
            'amount' => 1200,
            'balance_available_before' => 8000,
            'balance_available_after' => 6800,
            'balance_held_before' => 0,
            'balance_held_after' => 1200,
            'idempotency_key' => 'demo-wallet-hold-eur',
            'reason' => 'manual_demo_hold',
            'status' => 'completed',
            'metadata' => [
                'purpose' => 'wallet_hold_demo',
                'customer_email' => $customerTwo->email,
            ],
        ]);
    }
}
