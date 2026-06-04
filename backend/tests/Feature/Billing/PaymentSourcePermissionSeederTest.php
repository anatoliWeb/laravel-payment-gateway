<?php

namespace Tests\Feature\Billing;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\BillingPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSourcePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_source_provider_and_idempotency_permissions_are_admin_only_and_idempotent(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(BillingPermissionSeeder::class);
        $this->seed(BillingPermissionSeeder::class);

        $permissions = [
            'billing.payment_sources.use.wallet',
            'billing.payment_sources.use.payment_method',
            'billing.payment_sources.use.wallet_first',
            'billing.payment_sources.use.manual_invoice',
            'billing.payment_sources.use.simulator',
            'billing.providers.use.simulator',
            'billing.providers.use.manual',
            'billing.providers.use.internal_wallet',
            'billing.idempotency.view',
            'billing.idempotency.manage',
        ];
        $admin = Role::query()->where('name', 'admin')->firstOrFail();
        $user = Role::query()->where('name', 'user')->firstOrFail();

        foreach ($permissions as $permission) {
            $this->assertSame(1, Permission::query()->where('name', $permission)->count());
            $this->assertTrue($admin->permissions()->where('name', $permission)->exists());
            $this->assertFalse($user->permissions()->where('name', $permission)->exists());
        }
    }
}
