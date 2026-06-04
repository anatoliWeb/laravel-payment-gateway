<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_wallet_balances_and_transactions(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        app(WalletTransactionService::class)->credit($user, 'USD', 2500, metadata: ['source' => 'test']);

        $this->getJson('/api/v1/billing/wallet')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.balances.0.available_amount', 2500);

        $this->getJson('/api/v1/billing/wallet/balances')
            ->assertOk()
            ->assertJsonPath('data.0.currency.code', 'USD')
            ->assertJsonPath('data.0.available_amount', 2500);

        $this->getJson('/api/v1/billing/wallet/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'top_up')
            ->assertJsonPath('data.0.metadata.source', 'test')
            ->assertJsonMissingPath('data.0.idempotency_key');
    }

    public function test_unauthenticated_user_is_blocked_from_wallet_api(): void
    {
        $this->getJson('/api/v1/billing/wallet')->assertUnauthorized();
    }

    public function test_wallet_top_up_requires_idempotency_key_and_validates_payload(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/billing/wallet/top-ups', [
            'amount' => 1000,
            'currency' => 'USD',
        ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'idempotency_key_required');

        $this->withHeader('Idempotency-Key', 'wallet-top-up-invalid')
            ->postJson('/api/v1/billing/wallet/top-ups', [
                'amount' => 0,
                'currency' => 'US',
                'card_number' => '4242424242424242',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'currency', 'card_number']);
    }

    public function test_wallet_top_up_creates_simulator_payment_and_wallet_transaction(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        $paymentMethod = PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);

        $response = $this->withHeader('Idempotency-Key', 'wallet-top-up-success')
            ->postJson('/api/v1/billing/wallet/top-ups', [
                'amount' => 3000,
                'currency' => 'USD',
                'payment_method_id' => $paymentMethod->id,
            ])->assertCreated()
            ->assertJsonPath('data.payment.payment_source', 'payment_method')
            ->assertJsonPath('data.wallet_transaction.type', 'top_up')
            ->assertJsonPath('data.wallet_transaction.amount', 3000);

        $this->assertSame(3000, WalletTransaction::query()->where('type', 'top_up')->firstOrFail()->amount);
        $this->assertSame(1, Payment::query()->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'billing.wallet_top_up_succeeded',
        ]);
        $this->assertNotEmpty($response->json('data.payment.uuid'));
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function activeCurrency(string $code): Currency
    {
        return Currency::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => "{$code} Currency",
                'symbol' => $code,
                'decimal_precision' => 2,
                'is_active' => true,
                'is_base' => $code === 'USD',
                'description' => 'Test currency.',
                'metadata' => ['source' => 'test'],
            ],
        );
    }
}
