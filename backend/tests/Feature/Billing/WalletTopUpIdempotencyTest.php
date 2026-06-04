<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletTopUpIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_key_replays_top_up_without_duplicate_credit(): void
    {
        [$user, $method] = $this->readyUser();
        $payload = ['amount' => 2000, 'currency' => 'USD', 'payment_method_id' => $method->id];

        $first = $this->withHeader('Idempotency-Key', 'top-up-replay')->postJson('/api/v1/billing/wallet/top-ups', $payload)->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'top-up-replay')->postJson('/api/v1/billing/wallet/top-ups', $payload)->assertCreated();

        $this->assertSame($first->json('data.payment.uuid'), $second->json('data.payment.uuid'));
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, WalletTransaction::query()->where('type', 'top_up')->count());
        $this->assertSame(2000, app(WalletService::class)->getBalance($user->refresh(), 'USD')->available_amount);
        $this->assertDatabaseHas('idempotency_keys', ['user_id' => $user->id, 'scope' => 'wallet.top_up', 'status' => 'completed']);
    }

    public function test_same_top_up_key_with_different_payload_conflicts(): void
    {
        [$user, $method] = $this->readyUser();

        $this->withHeader('Idempotency-Key', 'top-up-conflict')
            ->postJson('/api/v1/billing/wallet/top-ups', ['amount' => 2000, 'currency' => 'USD', 'payment_method_id' => $method->id])
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'top-up-conflict')
            ->postJson('/api/v1/billing/wallet/top-ups', ['amount' => 3000, 'currency' => 'USD', 'payment_method_id' => $method->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'idempotency_key_conflict');

        $this->assertSame(2000, app(WalletService::class)->getBalance($user->refresh(), 'USD')->available_amount);
        $this->assertSame(1, IdempotencyKey::query()->where('scope', 'wallet.top_up')->count());
    }

    private function readyUser(): array
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Currency::query()->firstOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_precision' => 2,
                'is_active' => true,
                'is_base' => true,
                'description' => 'Test currency.',
                'metadata' => ['source' => 'test'],
            ],
        );
        $method = PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);

        return [$user, $method];
    }
}
