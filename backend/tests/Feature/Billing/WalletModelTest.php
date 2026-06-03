<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_model_relations_and_casts_work(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'metadata' => ['source' => 'test'],
        ]);
        $currency = Currency::factory()->usd()->base()->create();
        $balance = WalletBalance::factory()->create([
            'wallet_id' => $wallet->id,
            'currency_id' => $currency->id,
            'available_amount' => 5000,
            'held_amount' => 1000,
            'metadata' => ['balance' => 'test'],
        ]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);
        $transaction = WalletTransaction::factory()->forBalance($balance)->create([
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
            'amount' => 1200,
            'balance_available_before' => 5000,
            'balance_available_after' => 3800,
            'metadata' => ['ledger' => 'test'],
        ]);

        $this->assertTrue($user->wallet->is($wallet));
        $this->assertTrue($wallet->user->is($user));
        $this->assertSame(['source' => 'test'], $wallet->metadata);
        $this->assertSame(1, $wallet->balances()->count());
        $this->assertSame(1, $wallet->transactions()->count());
        $this->assertTrue($balance->wallet->is($wallet));
        $this->assertTrue($balance->currency->is($currency));
        $this->assertSame(5000, $balance->available_amount);
        $this->assertSame(1000, $balance->held_amount);
        $this->assertSame(['balance' => 'test'], $balance->metadata);
        $this->assertTrue($transaction->wallet->is($wallet));
        $this->assertTrue($transaction->walletBalance->is($balance));
        $this->assertTrue($transaction->currency->is($currency));
        $this->assertTrue($transaction->payment->is($payment));
        $this->assertTrue($transaction->subscription->is($subscription));
        $this->assertSame(1200, $transaction->amount);
        $this->assertSame(5000, $transaction->balance_available_before);
        $this->assertSame(3800, $transaction->balance_available_after);
        $this->assertSame(['ledger' => 'test'], $transaction->metadata);
    }
}
