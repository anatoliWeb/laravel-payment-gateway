<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Billing\BillingErrorCatalog;
use App\Services\Billing\WalletTransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingDomainExceptionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_known_billing_codes_resolve_to_stable_statuses_and_messages(): void
    {
        $this->assertSame(422, BillingErrorCatalog::statusFor('idempotency_key_conflict'));
        $this->assertSame(422, BillingErrorCatalog::statusFor('insufficient_wallet_balance'));
        $this->assertSame(422, BillingErrorCatalog::statusFor('subscription_inactive'));
        $this->assertSame(503, BillingErrorCatalog::statusFor('provider_not_configured'));
        $this->assertSame(503, BillingErrorCatalog::statusFor('provider_timeout'));
        $this->assertSame(403, BillingErrorCatalog::statusFor('feature_limit_exceeded'));

        $this->assertSame('Payment method not found.', BillingErrorCatalog::messageFor('payment_method_not_found'));
        $this->assertSame('Provider is not configured.', BillingErrorCatalog::messageFor('provider_not_configured'));
        $this->assertSame('Subscription is inactive.', BillingErrorCatalog::messageFor('subscription_inactive'));
        $this->assertSame('idempotency_key_conflict', BillingErrorCatalog::normalizeCode('idempotency_key_conflict'));
        $this->assertSame('request_failed', BillingErrorCatalog::normalizeCode(''));
    }

    public function test_billing_endpoints_surface_stable_domain_codes(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);
        app(WalletTransactionService::class)->credit($user, 'USD', 5000);

        $this->withHeader('Idempotency-Key', 'billing-domain-conflict')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1200,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'billing-domain-conflict')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1300,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'idempotency_key_conflict')
            ->assertJsonPath('errors.code', 'idempotency_key_conflict');

        $this->withHeader('Idempotency-Key', 'billing-domain-insufficient')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 9000,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'insufficient_wallet_balance')
            ->assertJsonPath('errors.code', 'insufficient_wallet_balance');

        $inactive = PaymentMethod::factory()->fakeCard()->inactive()->create(['user_id' => $user->id]);
        $other = PaymentMethod::factory()->fakeCard()->create();

        $this->postJson("/api/v1/billing/payment-methods/{$other->id}/set-default")
            ->assertStatus(422)
            ->assertJsonPath('code', 'payment_method_does_not_belong_to_user');

        $this->postJson("/api/v1/billing/payment-methods/{$inactive->id}/set-default")
            ->assertStatus(422)
            ->assertJsonPath('code', 'payment_method_not_allowed');
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
