<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\IdempotencyKey;
use App\Models\Permission;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletAdjustmentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_adjustment_key_replays_without_duplicate_balance_change(): void
    {
        [$actor, $target] = $this->readyActors();
        $payload = $this->payload($target, 1500);

        $first = $this->withHeader('Idempotency-Key', 'adjustment-replay')->postJson('/api/v1/billing/wallet-adjustments', $payload)->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'adjustment-replay')->postJson('/api/v1/billing/wallet-adjustments', $payload)->assertCreated();

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertSame(1, WalletTransaction::query()->where('type', 'adjustment')->count());
        $this->assertSame(1500, app(WalletService::class)->getBalance($target->refresh(), 'USD')->available_amount);
        $this->assertDatabaseHas('idempotency_keys', ['user_id' => $actor->id, 'scope' => 'wallet.adjustment', 'status' => 'completed']);
    }

    public function test_same_adjustment_key_with_different_payload_conflicts(): void
    {
        [, $target] = $this->readyActors();

        $this->withHeader('Idempotency-Key', 'adjustment-conflict')
            ->postJson('/api/v1/billing/wallet-adjustments', $this->payload($target, 1500))
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'adjustment-conflict')
            ->postJson('/api/v1/billing/wallet-adjustments', $this->payload($target, 2000))
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'idempotency_key_conflict');

        $this->assertSame(1500, app(WalletService::class)->getBalance($target->refresh(), 'USD')->available_amount);
        $this->assertSame(1, IdempotencyKey::query()->where('scope', 'wallet.adjustment')->count());
    }

    private function readyActors(): array
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $permission = Permission::query()->create([
            'name' => 'billing.wallets.adjust',
            'description' => 'Adjust wallets',
        ]);
        $actor->permissions()->attach($permission);
        Sanctum::actingAs($actor);
        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_precision' => 2,
            'is_active' => true,
            'is_base' => true,
            'description' => 'Test currency.',
            'metadata' => ['source' => 'test'],
        ]);

        return [$actor, $target];
    }

    private function payload(User $target, int $amount): array
    {
        return [
            'user_id' => $target->id,
            'currency' => 'USD',
            'amount' => $amount,
            'direction' => 'credit',
            'reason' => 'Idempotency adjustment test',
        ];
    }
}
