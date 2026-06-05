<?php

namespace Tests\Feature\Billing;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\BillingPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InvoicePermissionSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const INVOICE_PERMISSIONS = [
        'billing.invoices.view',
        'billing.invoices.create',
        'billing.invoices.manage',
        'billing.invoices.pay',
        'billing.invoices.view_company',
        'billing.invoices.view_seller',
        'billing.invoices.manage_company',
        'billing.invoices.manage_seller',
    ];

    public function test_invoice_permissions_are_seeded_and_assigned_to_admin(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(BillingPermissionSeeder::class);

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $adminPermissions = $adminRole->permissions()->pluck('name')->all();

        foreach (self::INVOICE_PERMISSIONS as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
            $this->assertContains($permission, $adminPermissions);
        }
    }

    public function test_normal_user_does_not_receive_invoice_management_permissions(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(BillingPermissionSeeder::class);

        $userRole = Role::query()->where('name', 'user')->firstOrFail();
        $userPermissions = $userRole->permissions()->pluck('name')->all();

        $this->assertNotContains('billing.invoices.manage', $userPermissions);
        $this->assertNotContains('billing.invoices.manage_company', $userPermissions);
        $this->assertNotContains('billing.invoices.manage_seller', $userPermissions);
    }

    public function test_invoice_permission_seeder_is_idempotent(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(BillingPermissionSeeder::class);
        $this->seed(BillingPermissionSeeder::class);

        foreach (self::INVOICE_PERMISSIONS as $permission) {
            $this->assertSame(1, Permission::query()->where('name', $permission)->count());
        }
    }
}
