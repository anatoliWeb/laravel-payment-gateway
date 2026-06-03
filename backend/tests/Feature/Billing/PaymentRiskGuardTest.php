<?php

namespace Tests\Feature\Billing;

use App\Models\ActivityLog;
use App\Models\BillingRestriction;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use App\Services\Billing\WalletTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentRiskGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_blocked_user_cannot_create_payment_and_wallet_is_not_debited(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        app(WalletTransactionService::class)->credit($user, 'USD', 5000);
        BillingRestriction::factory()->paymentBlocked()->create(['user_id' => $user->id]);

        $this->withHeader('Idempotency-Key', 'risk-blocked-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_blocked');

        $balance = app(WalletService::class)->getBalance($user, 'USD');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(5000, $balance->available_amount);
        $this->assertSame(1, WalletTransaction::query()->where('direction', 'credit')->count());
        $this->assertSame(0, WalletTransaction::query()->where('type', 'debit')->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'billing.payment_risk_blocked',
        ]);
    }

    public function test_too_many_failed_attempts_blocks_payment_creation(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');

        Payment::factory()->count(5)->failed()->create([
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(10),
        ]);

        $this->withHeader('Idempotency-Key', 'risk-failed-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'too_many_failed_attempts');
    }

    public function test_too_many_total_attempts_blocks_payment_creation(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');

        Payment::factory()->count(20)->pending()->create([
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(15),
        ]);

        $this->withHeader('Idempotency-Key', 'risk-attempts-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'too_many_payment_attempts');
    }

    public function test_demo_max_amount_blocks_payment_creation(): void
    {
        $this->actingUser();
        $this->activeCurrency('USD');

        $this->withHeader('Idempotency-Key', 'risk-amount-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1_000_001,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'amount_exceeds_demo_limit');
    }

    public function test_suspicious_metadata_blocks_and_logs_suspicious_attempt(): void
    {
        $this->actingUser();
        $this->activeCurrency('USD');

        $metadata = [];
        foreach (range(1, 26) as $index) {
            $metadata["safe_key_{$index}"] = "value_{$index}";
        }

        $this->withHeader('Idempotency-Key', 'risk-suspicious-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'wallet',
                'metadata' => $metadata,
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'suspicious_activity');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'billing.payment_risk_blocked',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'billing.payment_suspicious_attempt',
        ]);
    }

    public function test_normal_user_under_risk_limits_can_create_payment(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        app(WalletTransactionService::class)->credit($user, 'USD', 5000);

        $this->withHeader('Idempotency-Key', 'risk-normal-1')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'wallet',
            ])->assertCreated()
            ->assertJsonPath('data.status', 'succeeded');
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
