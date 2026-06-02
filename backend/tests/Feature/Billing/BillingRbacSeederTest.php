<?php

namespace Tests\Feature\Billing;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\BillingPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingRbacSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_permissions_are_seeded_and_assigned_to_admin_role(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(BillingPermissionSeeder::class);

        foreach (array_keys(BillingPermissionSeeder::PERMISSIONS) as $permissionName) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permissionName,
            ]);
        }

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $adminPermissionNames = $adminRole->permissions()->pluck('name')->all();

        foreach (array_keys(BillingPermissionSeeder::PERMISSIONS) as $permissionName) {
            $this->assertContains($permissionName, $adminPermissionNames);
        }
    }

    public function test_normal_user_role_does_not_receive_admin_billing_permissions_by_default(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(BillingPermissionSeeder::class);

        $userRole = Role::query()->where('name', 'user')->firstOrFail();
        $userPermissionNames = $userRole->permissions()->pluck('name')->all();

        foreach (array_keys(BillingPermissionSeeder::PERMISSIONS) as $permissionName) {
            $this->assertNotContains($permissionName, $userPermissionNames);
        }
    }

    public function test_billing_permission_seeder_is_idempotent(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(BillingPermissionSeeder::class);
        $this->seed(BillingPermissionSeeder::class);

        foreach (array_keys(BillingPermissionSeeder::PERMISSIONS) as $permissionName) {
            $this->assertSame(1, Permission::query()->where('name', $permissionName)->count());
        }

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $billingPermissionIds = Permission::query()
            ->whereIn('name', array_keys(BillingPermissionSeeder::PERMISSIONS))
            ->pluck('id')
            ->all();

        foreach ($billingPermissionIds as $permissionId) {
            $this->assertDatabaseHas('permission_role', [
                'role_id' => $adminRole->id,
                'permission_id' => $permissionId,
            ]);
        }
    }
}
