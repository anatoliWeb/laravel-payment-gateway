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
        'billing.subscriptions.view_any' => 'View all subscriptions',
        'billing.subscriptions.create' => 'Create subscriptions',
        'billing.subscriptions.manage' => 'Manage subscriptions',
        'billing.subscriptions.cancel' => 'Cancel subscriptions',
        'billing.subscriptions.change_plan' => 'Change subscription plans',
        'billing.subscriptions.renew' => 'Renew subscriptions',
        'billing.subscriptions.view_company' => 'View company-scoped subscriptions',
        'billing.subscriptions.view_seller' => 'View seller-scoped subscriptions',
        'billing.subscriptions.manage_company' => 'Manage company-scoped subscriptions',
        'billing.subscriptions.manage_seller' => 'Manage seller-scoped subscriptions',
        'billing.usage.view' => 'View billing usage',
        'billing.usage.manage' => 'Manage billing usage',
        'billing.overrides.view' => 'View billing feature overrides',
        'billing.overrides.manage' => 'Manage billing feature overrides',
        'billing.feature_overrides.view' => 'View billing feature overrides',
        'billing.feature_overrides.view_any' => 'View all billing feature overrides',
        'billing.feature_overrides.create' => 'Create billing feature overrides',
        'billing.feature_overrides.update' => 'Update billing feature overrides',
        'billing.feature_overrides.disable' => 'Disable billing feature overrides',
        'billing.restrictions.view' => 'View billing restrictions',
        'billing.restrictions.manage' => 'Manage billing restrictions',
        'billing.restrictions.create' => 'Create billing restrictions',
        'billing.restrictions.update' => 'Update billing restrictions',
        'billing.restrictions.disable' => 'Disable billing restrictions',
        'billing.payments.view' => 'View billing payments',
        'billing.payments.view_any' => 'View all billing payments',
        'billing.payments.view_transactions' => 'View payment transaction history',
        'billing.payments.create' => 'Create billing payments',
        'billing.payments.simulate' => 'Simulate payment outcomes',
        'billing.payments.refund' => 'Refund billing payments',
        'billing.payments.view_wallet_transactions' => 'View wallet-linked payment transactions',
        'billing.webhooks.view' => 'View billing webhook deliveries',
        'billing.webhooks.view_any' => 'View all billing webhook deliveries',
        'billing.webhooks.retry' => 'Retry billing webhook deliveries',
        'billing.invoices.view' => 'View billing invoices',
        'billing.invoices.view_any' => 'View all billing invoices',
        'billing.invoices.create' => 'Create billing invoices',
        'billing.invoices.manage' => 'Manage billing invoices',
        'billing.invoices.pay' => 'Pay billing invoices',
        'billing.invoices.view_company' => 'View company-scoped invoices',
        'billing.invoices.view_seller' => 'View seller-scoped invoices',
        'billing.invoices.manage_company' => 'Manage company-scoped invoices',
        'billing.invoices.manage_seller' => 'Manage seller-scoped invoices',
        'billing.wallets.view' => 'View user wallets',
        'billing.wallets.view_any' => 'View all user wallets',
        'billing.wallets.view_transactions' => 'View wallet transaction history',
        'billing.wallets.manage' => 'Manage user wallets',
        'billing.wallets.adjust' => 'Adjust user wallet balances',
        'billing.wallets.credit' => 'Credit user wallet balances',
        'billing.wallets.debit' => 'Debit user wallet balances',
        'billing.payment_sources.use.wallet' => 'Use wallet payment source',
        'billing.payment_sources.use.payment_method' => 'Use saved payment method source',
        'billing.payment_sources.use.wallet_first' => 'Use wallet-first payment source',
        'billing.payment_sources.use.manual_invoice' => 'Use manual invoice payment source',
        'billing.payment_sources.use.simulator' => 'Use simulator payment source',
        'billing.providers.use.simulator' => 'Use simulator payment provider',
        'billing.providers.use.manual' => 'Use manual payment provider',
        'billing.providers.use.internal_wallet' => 'Use internal wallet payment provider',
        'billing.idempotency.view' => 'View payment idempotency records',
        'billing.idempotency.view_any' => 'View all payment idempotency records',
        'billing.idempotency.manage' => 'Manage payment idempotency records',
        'billing.companies.view' => 'View billing companies',
        'billing.companies.manage' => 'Manage billing companies',
        'billing.companies.reports.view' => 'View company billing reports',
        'billing.sellers.view' => 'View billing sellers',
        'billing.sellers.manage' => 'Manage billing sellers',
        'billing.sellers.reports.view' => 'View seller billing reports',
        'billing.payments.view_company' => 'View company-scoped payments',
        'billing.payments.view_seller' => 'View seller-scoped payments',
        'billing.payments.manage_company' => 'Manage company-scoped payments',
        'billing.payments.manage_seller' => 'Manage seller-scoped payments',
        'billing.provider_accounts.view_any' => 'View all provider accounts',
        'billing.provider_accounts.view' => 'View provider accounts',
        'billing.provider_accounts.create' => 'Create provider accounts',
        'billing.provider_accounts.update' => 'Update provider accounts',
        'billing.provider_accounts.manage_company' => 'Manage company provider accounts',
        'billing.provider_accounts.manage_seller' => 'Manage seller provider accounts',
        'billing.provider_accounts.view_company' => 'View company provider accounts',
        'billing.provider_accounts.view_seller' => 'View seller provider accounts',
        'billing.restrictions.view_any' => 'View all billing restrictions',
        'billing.overrides.view_any' => 'View all billing feature overrides',
        'billing.currencies.view' => 'View billing currencies',
        'billing.currencies.manage' => 'Manage billing currencies',
        'billing.reports.view' => 'View billing reports',
        'billing.reports.view_financials' => 'View financial billing reports',
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
