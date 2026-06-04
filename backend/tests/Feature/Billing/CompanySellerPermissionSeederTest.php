<?php

namespace Tests\Feature\Billing;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\BillingPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySellerPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_seller_scope_permissions_are_admin_only_and_idempotent(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(BillingPermissionSeeder::class);
        $this->seed(BillingPermissionSeeder::class);

        $permissions = [
            'billing.companies.view',
            'billing.companies.manage',
            'billing.companies.reports.view',
            'billing.sellers.view',
            'billing.sellers.manage',
            'billing.sellers.reports.view',
            'billing.payments.view_company',
            'billing.payments.view_seller',
            'billing.payments.manage_company',
            'billing.payments.manage_seller',
            'billing.provider_accounts.manage_company',
            'billing.provider_accounts.manage_seller',
            'billing.provider_accounts.view_company',
            'billing.provider_accounts.view_seller',
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
