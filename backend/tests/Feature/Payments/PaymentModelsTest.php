<?php

use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('creates default payment persistence with aligned user and subscription', function () {
    $payment = Payment::factory()->create();

    expect($payment->subscription)->not->toBeNull();
    expect($payment->user)->not->toBeNull();
    expect($payment->subscription->user_id)->toBe($payment->user_id);
});

it('creates payment with casts and relations', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create(['user_id' => $user->id]);

    $payment = Payment::factory()
        ->succeeded()
        ->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'metadata' => ['source' => 'model-test'],
            'amount' => '2900',
        ]);

    expect($payment->amount)->toBe(2900);
    expect($payment->metadata)->toBe(['source' => 'model-test']);
    expect($payment->paid_at)->toBeInstanceOf(Carbon::class);
    expect($payment->user->is($user))->toBeTrue();
    expect($payment->subscription->is($subscription))->toBeTrue();
});

it('supports payment retry lineage and related records', function () {
    $payment = Payment::factory()->create();
    $retry = Payment::factory()->create([
        'parent_payment_id' => $payment->id,
        'user_id' => $payment->user_id,
        'subscription_id' => $payment->subscription_id,
    ]);

    $transaction = PaymentTransaction::factory()->create(['payment_id' => $payment->id]);
    $delivery = WebhookDelivery::factory()->create([
        'payment_id' => $payment->id,
        'subscription_id' => $payment->subscription_id,
    ]);

    expect($retry->parentPayment->is($payment))->toBeTrue();
    expect($payment->retryPayments()->first()->is($retry))->toBeTrue();
    expect($payment->transactions()->first()->is($transaction))->toBeTrue();
    expect($payment->webhookDeliveries()->first()->is($delivery))->toBeTrue();
});

it('creates payment transaction with casts and payment relation', function () {
    $payment = Payment::factory()->create();
    $transaction = PaymentTransaction::factory()
        ->succeeded()
        ->create([
            'payment_id' => $payment->id,
            'payload' => ['event' => 'payment_succeeded'],
            'amount' => '2900',
        ]);

    expect($transaction->amount)->toBe(2900);
    expect($transaction->payload)->toBe(['event' => 'payment_succeeded']);
    expect($transaction->payment->is($payment))->toBeTrue();
});

it('creates idempotency key with casts and morph relation', function () {
    $payment = Payment::factory()->create();

    $idempotencyKey = IdempotencyKey::factory()
        ->completed()
        ->create([
            'response_body' => ['payment_id' => $payment->id],
            'response_status' => '201',
            'related_type' => Payment::class,
            'related_id' => $payment->id,
            'locked_until' => now()->addMinute(),
            'expires_at' => now()->addHour(),
        ]);

    expect($idempotencyKey->response_body)->toBe(['payment_id' => $payment->id]);
    expect($idempotencyKey->response_status)->toBe(201);
    expect($idempotencyKey->locked_until)->toBeInstanceOf(Carbon::class);
    expect($idempotencyKey->expires_at)->toBeInstanceOf(Carbon::class);
    expect($idempotencyKey->related->is($payment))->toBeTrue();
});

it('creates webhook delivery with casts and relations', function () {
    $payment = Payment::factory()->create();
    $delivery = WebhookDelivery::factory()
        ->failed()
        ->create([
            'payment_id' => $payment->id,
            'subscription_id' => $payment->subscription_id,
            'payload' => ['event' => 'payment.failed'],
            'metadata' => ['attempt' => 'first'],
        ]);

    expect($delivery->payload)->toBe(['event' => 'payment.failed']);
    expect($delivery->metadata)->toBe(['attempt' => 'first']);
    expect($delivery->attempts)->toBe(1);
    expect($delivery->max_attempts)->toBe(3);
    expect($delivery->next_retry_at)->toBeInstanceOf(Carbon::class);
    expect($delivery->last_attempt_at)->toBeInstanceOf(Carbon::class);
    expect($delivery->failed_at)->toBeInstanceOf(Carbon::class);
    expect($delivery->payment->is($payment))->toBeTrue();
    expect($delivery->subscription->is($payment->subscription))->toBeTrue();
});

it('creates default webhook delivery persistence with aligned payment context', function () {
    $delivery = WebhookDelivery::factory()->create();

    expect($delivery->payment)->not->toBeNull();
    expect($delivery->subscription)->not->toBeNull();
    expect($delivery->subscription_id)->toBe($delivery->payment->subscription_id);
});

it('exposes subscription payment history relation', function () {
    $subscription = Subscription::factory()->create();
    $payment = Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'user_id' => $subscription->user_id,
    ]);

    expect($subscription->payments()->first()->is($payment))->toBeTrue();
});
