<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletTransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentCreationFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_creation_requires_idempotency_key(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');

        $this->postJson('/api/v1/billing/payments', [
            'amount' => 1000,
            'currency' => 'USD',
            'payment_source' => 'wallet',
        ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'idempotency_key_required');
    }

    public function test_payment_creation_rejects_invalid_amount_currency_raw_fields_and_unsafe_metadata(): void
    {
        $this->actingUser();

        $this->withHeader('Idempotency-Key', 'invalid-shape-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => -10,
                'currency' => 'US',
                'card_number' => '4242424242424242',
                'metadata' => [
                    'private_key' => 'secret',
                ],
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'currency', 'card_number', 'metadata.private_key']);
    }

    public function test_it_creates_successful_wallet_payment_wallet_debit_and_activates_subscription(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        $subscription = Subscription::factory()->pending()->create(['user_id' => $user->id]);
        app(WalletTransactionService::class)->credit($user, 'USD', 5000);

        $response = $this->withHeader('Idempotency-Key', 'wallet-payment-1')
            ->postJson('/api/v1/billing/payments', [
                'subscription_id' => $subscription->id,
                'amount' => 2900,
                'currency' => 'USD',
                'payment_source' => 'wallet',
                'description' => 'Wallet payment test',
            ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.payment_source', 'wallet')
            ->assertJsonPath('data.provider', 'internal_wallet');

        $payment = Payment::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
        $walletTransaction = WalletTransaction::query()->where('payment_id', $payment->id)->firstOrFail();

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->current_period_start);
        $this->assertNotNull($subscription->current_period_end);
        $this->assertSame('debit', $walletTransaction->type);
        $this->assertSame(2900, $walletTransaction->amount);
        $this->assertSame($walletTransaction->id, $payment->metadata['wallet_transaction_id']);
        $this->assertSame(1, PaymentTransaction::query()->where('payment_id', $payment->id)->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'billing.payment_created',
        ]);
    }

    public function test_wallet_payment_fails_without_creating_payment_when_balance_is_insufficient(): void
    {
        $this->actingUser();
        $this->activeCurrency('USD');

        $this->withHeader('Idempotency-Key', 'wallet-insufficient-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 2000,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'insufficient_wallet_balance');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_it_creates_payment_method_payment_with_default_fake_card_and_safe_provider_reference(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        $paymentMethod = PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);

        $response = $this->withHeader('Idempotency-Key', 'card-payment-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 2900,
                'currency' => 'USD',
                'payment_source' => 'payment_method',
                'metadata' => ['source' => 'test'],
            ])->assertCreated()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.provider', 'simulator')
            ->assertJsonPath('data.payment_method_summary.id', $paymentMethod->id);

        $payment = Payment::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

        $this->assertStringStartsWith('sim_', $payment->provider_reference);
        $this->assertSame('fake_card', $payment->payment_method);
        $this->assertArrayNotHasKey('card_number', $payment->metadata);
        $this->assertSame(1, $payment->transactions()->count());
    }

    public function test_it_rejects_inactive_or_another_users_payment_method(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        $inactive = PaymentMethod::factory()->fakeCard()->inactive()->create(['user_id' => $user->id]);
        $other = PaymentMethod::factory()->fakeCard()->create();

        $this->withHeader('Idempotency-Key', 'inactive-method-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'payment_method',
                'payment_method_id' => $inactive->id,
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_method_not_found');

        $this->withHeader('Idempotency-Key', 'other-method-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'payment_method',
                'payment_method_id' => $other->id,
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_method_does_not_belong_to_user');
    }

    public function test_wallet_first_uses_wallet_when_enough_balance_and_falls_back_to_payment_method(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);
        app(WalletTransactionService::class)->credit($user, 'USD', 3000);

        $walletResponse = $this->withHeader('Idempotency-Key', 'wallet-first-wallet-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'wallet_first',
            ])->assertCreated();

        $fallbackResponse = $this->withHeader('Idempotency-Key', 'wallet-first-card-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 5000,
                'currency' => 'USD',
                'payment_source' => 'wallet_first',
            ])->assertCreated();

        $this->assertSame('wallet', $walletResponse->json('data.payment_source'));
        $this->assertSame('wallet_first', Payment::query()->where('uuid', $fallbackResponse->json('data.uuid'))->first()->metadata['payment_source']);
        $this->assertSame('simulator', $fallbackResponse->json('data.provider'));
    }

    public function test_wallet_first_fails_when_wallet_insufficient_and_no_payment_method_exists(): void
    {
        $this->actingUser();
        $this->activeCurrency('USD');

        $this->withHeader('Idempotency-Key', 'wallet-first-none-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'wallet_first',
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_method_not_found');
    }

    public function test_plan_context_derives_amount_and_rejects_currency_conflict(): void
    {
        $this->actingUser();
        $this->activeCurrency('USD');
        $this->activeCurrency('EUR');
        $plan = Plan::factory()->create([
            'slug' => 'phase-13-plan',
            'price_amount' => 4500,
            'currency' => 'USD',
        ]);

        $this->withHeader('Idempotency-Key', 'plan-conflict-1')
            ->postJson('/api/v1/billing/payments', [
                'plan_slug' => $plan->slug,
                'currency' => 'EUR',
                'payment_source' => 'wallet',
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_currency_conflict');
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function activeCurrency(string $code): Currency
    {
        return Currency::factory()->create([
            'code' => $code,
            'name' => "{$code} Currency",
            'symbol' => $code,
            'is_active' => true,
            'is_base' => $code === 'USD',
        ]);
    }
}
