<?php

namespace Tests\Feature\Billing;

use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use App\Services\Billing\WalletTransactionService;
use Database\Seeders\BillingPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletAdjustmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_actor_with_adjust_permission_can_credit_wallet_with_auditable_context(): void
    {
        $actor = $this->actorWithPermissions(['billing.wallets.adjust']);
        $target = User::factory()->create();
        $this->activeCurrency('USD');

        $response = $this->withHeader('Idempotency-Key', 'wallet-adjustment-credit-1')
            ->postJson('/api/v1/billing/wallet-adjustments', [
                'user_id' => $target->id,
                'currency' => 'USD',
                'amount' => 2500,
                'direction' => 'credit',
                'reason' => 'Support-approved balance correction',
                'description' => 'Customer billing reconciliation.',
                'reference' => 'ticket-1001',
                'metadata' => ['case_type' => 'support'],
            ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'adjustment')
            ->assertJsonPath('data.direction', 'credit')
            ->assertJsonPath('data.amount', 2500)
            ->assertJsonPath('data.reference', 'ticket-1001')
            ->assertJsonPath('data.metadata.actor_id', $actor->id)
            ->assertJsonPath('data.metadata.adjustment_type', 'manual_credit')
            ->assertJsonMissingPath('data.idempotency_key');

        $transaction = WalletTransaction::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

        $this->assertSame(0, $transaction->balance_available_before);
        $this->assertSame(2500, $transaction->balance_available_after);
        $this->assertSame(2500, app(WalletService::class)->getBalance($target->refresh(), 'USD')->available_amount);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'billing.wallet_manual_credit',
        ]);
    }

    public function test_actor_with_adjust_permission_can_credit_and_debit(): void
    {
        $this->actorWithPermissions(['billing.wallets.adjust']);
        $target = User::factory()->create();
        $this->activeCurrency('USD');

        $this->withHeader('Idempotency-Key', 'adjust-permission-credit')
            ->postJson('/api/v1/billing/wallet-adjustments', $this->payload($target, 'credit'))
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'adjust-permission-debit')
            ->postJson('/api/v1/billing/wallet-adjustments', $this->payload($target, 'debit'))
            ->assertCreated();

        $this->assertSame(0, app(WalletService::class)->getBalance($target->refresh(), 'USD')->available_amount);
    }

    public function test_actor_with_debit_permission_can_debit_wallet_and_insufficient_balance_is_blocked(): void
    {
        $actor = $this->actorWithPermissions(['billing.wallets.debit']);
        $target = User::factory()->create();
        $this->activeCurrency('USD');
        app(WalletTransactionService::class)->credit($target, 'USD', 3000);

        $this->withHeader('Idempotency-Key', 'wallet-adjustment-debit-1')
            ->postJson('/api/v1/billing/wallet-adjustments', [
                'user_id' => $target->id,
                'currency' => 'USD',
                'amount' => 1200,
                'direction' => 'debit',
                'reason' => 'Reverse duplicated support credit',
            ])->assertCreated()
            ->assertJsonPath('data.direction', 'debit')
            ->assertJsonPath('data.balance_available_before', 3000)
            ->assertJsonPath('data.balance_available_after', 1800);

        $this->withHeader('Idempotency-Key', 'wallet-adjustment-debit-too-large')
            ->postJson('/api/v1/billing/wallet-adjustments', [
                'user_id' => $target->id,
                'currency' => 'USD',
                'amount' => 5000,
                'direction' => 'debit',
                'reason' => 'Attempt excessive debit',
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'insufficient_wallet_balance');

        $this->assertSame(1800, app(WalletService::class)->getBalance($target->refresh(), 'USD')->available_amount);
        $this->assertSame(1, ActivityLog::query()->where('action', 'billing.wallet_manual_debit')->count());
    }

    public function test_manual_adjustment_is_permission_protected_by_direction(): void
    {
        $target = User::factory()->create();
        $this->activeCurrency('USD');

        Sanctum::actingAs(User::factory()->create());
        $this->withHeader('Idempotency-Key', 'no-permission')
            ->postJson('/api/v1/billing/wallet-adjustments', $this->payload($target, 'credit'))
            ->assertForbidden();

        $creditOnlyActor = $this->actorWithPermissions(['billing.wallets.credit']);
        $this->withHeader('Idempotency-Key', 'credit-only')
            ->postJson('/api/v1/billing/wallet-adjustments', $this->payload($target, 'credit'))
            ->assertCreated();

        Sanctum::actingAs($creditOnlyActor);
        $this->withHeader('Idempotency-Key', 'credit-cannot-debit')
            ->postJson('/api/v1/billing/wallet-adjustments', $this->payload($target, 'debit'))
            ->assertForbidden();
    }

    public function test_old_admin_namespace_endpoint_is_not_available(): void
    {
        $this->actorWithPermissions(['billing.wallets.adjust']);
        $target = User::factory()->create();

        $this->withHeader('Idempotency-Key', 'old-admin-route')
            ->postJson('/api/v1/admin/billing/wallet-adjustments', $this->payload($target, 'credit'))
            ->assertNotFound();
    }

    public function test_request_requires_reason_and_rejects_unsafe_metadata(): void
    {
        $this->actorWithPermissions(['billing.wallets.adjust']);
        $target = User::factory()->create();

        $this->postJson('/api/v1/billing/wallet-adjustments', [
            'user_id' => $target->id,
            'currency' => 'USD',
            'amount' => 1000,
            'direction' => 'credit',
            'metadata' => ['secret' => 'unsafe'],
            'card_number' => '4242424242424242',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['reason', 'metadata.secret', 'card_number']);
    }

    public function test_request_returns_stable_error_when_idempotency_key_is_missing(): void
    {
        $this->actorWithPermissions(['billing.wallets.adjust']);
        $target = User::factory()->create();
        $this->activeCurrency('USD');

        $this->postJson('/api/v1/billing/wallet-adjustments', $this->payload($target, 'credit'))
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'idempotency_key_required');
    }

    public function test_repeated_idempotency_key_does_not_duplicate_adjustment_or_activity(): void
    {
        $actor = $this->actorWithPermissions(['billing.wallets.adjust']);
        $target = User::factory()->create();
        $this->activeCurrency('USD');
        $payload = $this->payload($target, 'credit');

        $first = $this->withHeader('Idempotency-Key', 'same-wallet-adjustment')
            ->postJson('/api/v1/billing/wallet-adjustments', $payload)
            ->assertCreated();

        $second = $this->withHeader('Idempotency-Key', 'same-wallet-adjustment')
            ->postJson('/api/v1/billing/wallet-adjustments', $payload)
            ->assertCreated();

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertSame(1, WalletTransaction::query()->where('type', 'adjustment')->count());
        $this->assertSame(1000, app(WalletService::class)->getBalance($target->refresh(), 'USD')->available_amount);
        $this->assertSame(1, ActivityLog::query()
            ->where('user_id', $actor->id)
            ->where('action', 'billing.wallet_manual_credit')
            ->count());
    }

    public function test_reusing_idempotency_key_with_different_adjustment_is_rejected(): void
    {
        $this->actorWithPermissions(['billing.wallets.adjust']);
        $target = User::factory()->create();
        $this->activeCurrency('USD');

        $this->withHeader('Idempotency-Key', 'conflicting-wallet-adjustment')
            ->postJson('/api/v1/billing/wallet-adjustments', $this->payload($target, 'credit'))
            ->assertCreated();

        $conflictingPayload = $this->payload($target, 'credit');
        $conflictingPayload['amount'] = 2000;

        $this->withHeader('Idempotency-Key', 'conflicting-wallet-adjustment')
            ->postJson('/api/v1/billing/wallet-adjustments', $conflictingPayload)
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'idempotency_key_conflict');

        $this->assertSame(1, WalletTransaction::query()->where('type', 'adjustment')->count());
        $this->assertSame(1000, app(WalletService::class)->getBalance($target->refresh(), 'USD')->available_amount);
    }

    public function test_billing_permission_seeder_grants_adjustments_to_admin_and_not_default_user(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(BillingPermissionSeeder::class);

        $expected = [
            'billing.wallets.adjust',
            'billing.wallets.credit',
            'billing.wallets.debit',
        ];

        $admin = Role::query()->where('name', 'admin')->firstOrFail();
        $user = Role::query()->where('name', 'user')->firstOrFail();

        foreach ($expected as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
            $this->assertTrue($admin->permissions()->where('name', $permission)->exists());
            $this->assertFalse($user->permissions()->where('name', $permission)->exists());
        }

        $adminActor = User::query()->where('email', 'admin@test.com')->firstOrFail();
        $target = User::factory()->create();
        $this->activeCurrency('USD');
        Sanctum::actingAs($adminActor);

        $this->withHeader('Idempotency-Key', 'seeded-admin-adjustment')
            ->postJson('/api/v1/billing/wallet-adjustments', $this->payload($target, 'credit'))
            ->assertCreated();
    }

    private function actorWithPermissions(array $permissionNames): User
    {
        $actor = User::factory()->create();

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                ['description' => $permissionName],
            );
            $actor->permissions()->syncWithoutDetaching([$permission->id]);
        }

        Sanctum::actingAs($actor);

        return $actor;
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

    private function payload(User $target, string $direction): array
    {
        return [
            'user_id' => $target->id,
            'currency' => 'USD',
            'amount' => 1000,
            'direction' => $direction,
            'reason' => 'Permission-gated wallet adjustment test',
        ];
    }
}
