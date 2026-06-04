<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\PaymentProviderAccount;
use App\Models\Seller;
use App\Models\SellerCustomer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanySellerSeeder extends Seeder
{
    public const COMPANY_SLUG = 'demo-company';

    public const SELLER_SLUG = 'demo-seller';

    public const COMPANY_OWNER_EMAIL = 'demo-company-owner@example.com';

    public const SELLER_OWNER_EMAIL = 'demo-seller-owner@example.com';

    public const CUSTOMER_EMAIL = 'demo-customer@example.com';

    public function run(): void
    {
        $companyOwner = $this->firstOrCreateDemoUser(
            self::COMPANY_OWNER_EMAIL,
            'Demo Company Owner',
        );
        $sellerOwner = $this->firstOrCreateDemoUser(
            self::SELLER_OWNER_EMAIL,
            'Demo Seller Owner',
        );
        $customer = $this->firstOrCreateDemoUser(
            self::CUSTOMER_EMAIL,
            'Demo Customer',
        );

        $company = Company::query()->firstOrNew(['slug' => self::COMPANY_SLUG]);
        $company->fill([
            'name' => 'Demo Company',
            'status' => 'active',
            'metadata' => [
                'seeded' => true,
                'purpose' => 'demo_ownership_scope',
            ],
        ]);
        $company->uuid ??= (string) Str::uuid();
        $company->save();

        $seller = Seller::query()->firstOrNew(['slug' => self::SELLER_SLUG]);
        $seller->fill([
            'company_id' => $company->id,
            'owner_user_id' => $sellerOwner->id,
            'name' => 'Demo Seller',
            'status' => 'active',
            'metadata' => [
                'seeded' => true,
                'purpose' => 'demo_seller_scope',
            ],
        ]);
        $seller->uuid ??= (string) Str::uuid();
        $seller->save();

        CompanyUser::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $companyOwner->id,
            ],
            [
                'role' => 'owner',
                'status' => 'active',
                'metadata' => [
                    'seeded' => true,
                    'purpose' => 'demo_company_membership',
                ],
            ],
        );

        SellerCustomer::query()->updateOrCreate(
            [
                'seller_id' => $seller->id,
                'user_id' => $customer->id,
            ],
            [
                'status' => 'active',
                'metadata' => [
                    'seeded' => true,
                    'purpose' => 'demo_seller_customer',
                ],
            ],
        );

        $this->seedSimulatorProviderAccount($company, $seller, $sellerOwner);
    }

    private function firstOrCreateDemoUser(string $email, string $name): User
    {
        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            return $existing;
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function seedSimulatorProviderAccount(Company $company, Seller $seller, User $sellerOwner): void
    {
        $account = PaymentProviderAccount::query()->firstOrNew([
            'seller_id' => $seller->id,
            'provider' => 'simulator',
        ]);
        $account->fill([
            'user_id' => $sellerOwner->id,
            'company_id' => $company->id,
            'display_name' => 'Demo Seller Simulator',
            'status' => 'active',
            'mode' => 'test',
            'config_source' => 'database',
            'public_config' => [
                'simulator_safe' => true,
                'seeded' => true,
            ],
            'capabilities' => [
                'charge' => true,
                'refund' => true,
                'webhook_verification' => true,
            ],
            'metadata' => [
                'seeded' => true,
                'purpose' => 'demo_seller_provider_account',
            ],
        ]);
        $account->setCredentials([
            'api_key' => 'fake_demo_simulator_key_0000',
        ]);
        $account->uuid ??= (string) Str::uuid();
        $account->save();
    }
}
