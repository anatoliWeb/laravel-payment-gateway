<?php

namespace Database\Seeders\Billing;

use Database\Seeders\BillingPermissionSeeder;
use Database\Seeders\BillingSeeder;
use Database\Seeders\CompanySellerSeeder;
use Database\Seeders\CurrencySeeder;

class BillingDemoSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $this->call([
            BillingPermissionSeeder::class,
            CurrencySeeder::class,
            BillingSeeder::class,
            CompanySellerSeeder::class,
            BillingDemoUserSeeder::class,
            BillingDemoPlanSeeder::class,
            BillingDemoProviderAccountSeeder::class,
            BillingDemoWalletSeeder::class,
            BillingDemoSubscriptionSeeder::class,
            BillingDemoInvoiceSeeder::class,
            BillingDemoPaymentSeeder::class,
            BillingDemoWebhookSeeder::class,
            BillingDemoRestrictionSeeder::class,
            BillingDemoFeatureOverrideSeeder::class,
            BillingDemoReportDataSeeder::class,
        ]);
    }
}
