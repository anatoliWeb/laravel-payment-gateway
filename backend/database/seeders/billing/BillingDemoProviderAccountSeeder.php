<?php

namespace Database\Seeders\Billing;

use Database\Seeders\CompanySellerSeeder;

class BillingDemoProviderAccountSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $this->call(CompanySellerSeeder::class);

        $company = $this->demoCompany();
        $seller = $this->demoSeller();
        $companyOwner = $this->demoUser(self::COMPANY_OWNER_EMAIL);
        $sellerOwner = $this->demoUser(self::SELLER_OWNER_EMAIL);
        $admin = $this->demoUser(self::ADMIN_EMAIL);

        $this->upsertProviderAccount([
            'uuid' => 'demo-platform-simulator-account',
            'user_id' => $admin->id,
            'company_id' => $company->id,
            'seller_id' => null,
            'provider' => 'simulator',
            'display_name' => 'Demo Platform Simulator',
            'status' => 'active',
            'mode' => 'test',
            'config_source' => 'database',
            'public_config' => [
                'seeded' => true,
                'demo' => true,
            ],
            'capabilities' => [
                'charge' => true,
                'refund' => true,
                'webhook_verification' => true,
            ],
            'metadata' => [
                'purpose' => 'platform_simulator_account',
            ],
            'credentials' => [
                'api_key' => 'fake_platform_simulator_key_0000',
            ],
        ]);

        $this->upsertProviderAccount([
            'uuid' => 'demo-seller-simulator-account',
            'user_id' => $sellerOwner->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'provider' => 'simulator',
            'display_name' => 'Demo Seller Simulator',
            'status' => 'active',
            'mode' => 'test',
            'config_source' => 'database',
            'public_config' => [
                'seeded' => true,
                'demo' => true,
                'scope' => 'seller',
            ],
            'capabilities' => [
                'charge' => true,
                'refund' => true,
                'webhook_verification' => true,
            ],
            'metadata' => [
                'purpose' => 'seller_simulator_account',
            ],
            'credentials' => [
                'api_key' => 'fake_demo_simulator_key_0000',
            ],
        ]);

        $this->upsertProviderAccount([
            'uuid' => 'demo-company-owner-simulator-account',
            'user_id' => $companyOwner->id,
            'company_id' => $company->id,
            'seller_id' => null,
            'provider' => 'simulator',
            'display_name' => 'Demo Company Owner Simulator',
            'status' => 'inactive',
            'mode' => 'test',
            'config_source' => 'database',
            'public_config' => [
                'seeded' => true,
                'demo' => true,
                'scope' => 'company_owner_preview',
            ],
            'capabilities' => [
                'charge' => false,
                'refund' => false,
                'webhook_verification' => true,
            ],
            'metadata' => [
                'purpose' => 'company_owner_preview_account',
            ],
            'credentials' => [
                'api_key' => 'fake_company_owner_preview_key_0000',
            ],
        ]);
    }
}
