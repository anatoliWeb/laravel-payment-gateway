<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Billing\WalletTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletCardPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_api_supports_wallet_payment_source(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        app(WalletTransactionService::class)->credit($user, 'USD', 5000);

        $this->withHeader('Idempotency-Key', 'api-wallet-source')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1200,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])->assertCreated()
            ->assertJsonPath('data.payment_source', 'wallet')
            ->assertJsonPath('data.status', 'succeeded');
    }

    public function test_payment_api_supports_payment_method_source(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);

        $this->withHeader('Idempotency-Key', 'api-card-source')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1200,
                'currency' => 'USD',
                'payment_source' => 'payment_method',
            ])->assertCreated()
            ->assertJsonPath('data.payment_source', 'payment_method')
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_payment_api_supports_wallet_first_fallback(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);

        $this->withHeader('Idempotency-Key', 'api-wallet-first')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1200,
                'currency' => 'USD',
                'payment_source' => 'wallet_first',
            ])->assertCreated()
            ->assertJsonPath('data.payment_source', 'wallet_first')
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_payment_api_returns_stable_errors_for_source_failures(): void
    {
        $this->actingUser();
        $this->activeCurrency('USD');

        $this->withHeader('Idempotency-Key', 'api-wallet-insufficient')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1200,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'insufficient_wallet_balance');

        $this->withHeader('Idempotency-Key', 'api-method-missing')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1200,
                'currency' => 'USD',
                'payment_source' => 'payment_method',
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_method_not_found');

    }

    public function test_payment_api_requires_idempotency_key(): void
    {
        $this->actingUser();
        $this->activeCurrency('USD');

        $this->postJson('/api/v1/billing/payments', [
            'amount' => 1200,
            'currency' => 'USD',
            'payment_source' => 'payment_method',
        ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'idempotency_key_required');
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
