<?php

namespace Tests\Feature\Billing;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\BillingPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubscriptionPermissionSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_subscription_permissions_are_seeded_for_admin(): void
    {
        $this->seed(BillingPermissionSeeder::class);

        $permissions = [
            'billing.subscriptions.view',
            'billing.subscriptions.create',
            'billing.subscriptions.manage',
            'billing.subscriptions.cancel',
            'billing.subscriptions.change_plan',
            'billing.subscriptions.renew',
            'billing.subscriptions.view_company',
            'billing.subscriptions.view_seller',
            'billing.subscriptions.manage_company',
            'billing.subscriptions.manage_seller',
        ];

        foreach ($permissions as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
        }

        $admin = Role::query()->where('name', 'admin')->firstOrFail();
        $permissionIds = Permission::query()->whereIn('name', $permissions)->pluck('id')->all();

        foreach ($permissionIds as $permissionId) {
            $this->assertTrue($admin->permissions()->whereKey($permissionId)->exists());
        }
    }
}
