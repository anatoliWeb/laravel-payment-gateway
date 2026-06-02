<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class BillingPermissionSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    public const PERMISSIONS = [
        'billing.plans.view' => 'View billing plans',
        'billing.plans.manage' => 'Manage billing plans',
        'billing.subscriptions.view' => 'View subscriptions',
        'billing.subscriptions.manage' => 'Manage subscriptions',
        'billing.usage.view' => 'View billing usage',
        'billing.usage.manage' => 'Manage billing usage',
        'billing.overrides.view' => 'View billing feature overrides',
        'billing.overrides.manage' => 'Manage billing feature overrides',
        'billing.restrictions.view' => 'View billing restrictions',
        'billing.restrictions.manage' => 'Manage billing restrictions',
        'billing.payments.view' => 'View billing payments',
        'billing.payments.create' => 'Create billing payments',
        'billing.payments.simulate' => 'Simulate payment outcomes',
        'billing.payments.refund' => 'Refund billing payments',
        'billing.webhooks.view' => 'View billing webhook deliveries',
        'billing.webhooks.retry' => 'Retry billing webhook deliveries',
        'billing.wallets.view' => 'View user wallets',
        'billing.wallets.manage' => 'Manage user wallets',
        'billing.currencies.view' => 'View billing currencies',
        'billing.currencies.manage' => 'Manage billing currencies',
        'billing.reports.view' => 'View billing reports',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }

        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrator'],
        );

        $permissionIds = Permission::query()
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->pluck('id')
            ->all();

        // WHY: Keep existing admin permissions intact while granting new
        // billing capabilities added after the base RBAC seeder runs.
        $adminRole->permissions()->syncWithoutDetaching($permissionIds);
    }
}
