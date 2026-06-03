<?php

namespace Tests\Feature\Billing;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_model_relations_and_casts_work(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'amount' => '2900',
            'metadata' => ['source' => 'test'],
            'paid_at' => now(),
        ]);
        $transaction = PaymentTransaction::factory()->create([
            'payment_id' => $payment->id,
            'amount' => '2900',
            'payload' => ['safe' => true],
        ]);

        $this->assertTrue($payment->user->is($user));
        $this->assertTrue($payment->subscription->is($subscription));
        $this->assertTrue($payment->transactions()->first()->is($transaction));
        $this->assertSame(2900, $payment->amount);
        $this->assertSame(['source' => 'test'], $payment->metadata);
        $this->assertSame(2900, $transaction->amount);
        $this->assertSame(['safe' => true], $transaction->payload);
        $this->assertTrue($transaction->payment->is($payment));
    }
}
