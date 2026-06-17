<?php

namespace Database\Seeders\Billing;

use App\Models\Company;
use App\Models\Seller;
use Database\Seeders\BillingPermissionSeeder;
use Database\Seeders\CompanySellerSeeder;

class BillingDemoUserSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $this->call([
            BillingPermissionSeeder::class,
            CompanySellerSeeder::class,
        ]);

        $admin = $this->firstOrCreateDemoUser(self::ADMIN_EMAIL, 'Demo Billing Admin');
        $operator = $this->firstOrCreateDemoUser(self::OPERATOR_EMAIL, 'Demo Billing Operator');
        $normal = $this->firstOrCreateDemoUser(self::NORMAL_EMAIL, 'Demo Billing User');

        $this->firstOrCreateDemoUser(self::COMPANY_OWNER_EMAIL, 'Demo Company Owner');
        $this->firstOrCreateDemoUser(self::SELLER_OWNER_EMAIL, 'Demo Seller Owner');
        $legacyCustomer = $this->firstOrCreateDemoUser(self::PRIMARY_CUSTOMER_EMAIL, 'Demo Customer');
        $customerOne = $this->firstOrCreateDemoUser(self::CUSTOMER_ONE_EMAIL, 'Demo Customer 01');
        $customerTwo = $this->firstOrCreateDemoUser(self::CUSTOMER_TWO_EMAIL, 'Demo Customer 02');
        $customerThree = $this->firstOrCreateDemoUser(self::CUSTOMER_THREE_EMAIL, 'Demo Customer 03');

        $this->attachBillingPermissions($admin);
        $this->attachReadOnlyBillingPermissions($operator);

        $company = Company::query()->where('slug', self::COMPANY_SLUG)->firstOrFail();
        $seller = Seller::query()->where('slug', self::SELLER_SLUG)->firstOrFail();

        $this->upsertCompanyUser($company->id, $this->demoUser(self::COMPANY_OWNER_EMAIL)->id, 'owner', 'active', [
            'purpose' => 'demo_company_membership',
        ]);

        $this->upsertSellerCustomer($seller->id, $legacyCustomer->id, 'active', [
            'purpose' => 'demo_primary_customer',
        ]);
        $this->upsertSellerCustomer($seller->id, $customerOne->id, 'active', [
            'purpose' => 'demo_reporting_customer_01',
        ]);
        $this->upsertSellerCustomer($seller->id, $customerTwo->id, 'active', [
            'purpose' => 'demo_reporting_customer_02',
        ]);
        $this->upsertSellerCustomer($seller->id, $customerThree->id, 'active', [
            'purpose' => 'demo_reporting_customer_03',
        ]);
    }
}
