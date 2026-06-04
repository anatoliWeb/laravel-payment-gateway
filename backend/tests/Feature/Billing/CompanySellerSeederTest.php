<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\PaymentProviderAccount;
use App\Models\Seller;
use App\Models\SellerCustomer;
use App\Models\User;
use Database\Seeders\CompanySellerSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanySellerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_seller_seeder_creates_complete_demo_ownership_graph(): void
    {
        $this->seed(CompanySellerSeeder::class);

        $company = Company::query()->where('slug', CompanySellerSeeder::COMPANY_SLUG)->firstOrFail();
        $seller = Seller::query()->where('slug', CompanySellerSeeder::SELLER_SLUG)->firstOrFail();
        $companyOwner = User::query()->where('email', CompanySellerSeeder::COMPANY_OWNER_EMAIL)->firstOrFail();
        $sellerOwner = User::query()->where('email', CompanySellerSeeder::SELLER_OWNER_EMAIL)->firstOrFail();
        $customer = User::query()->where('email', CompanySellerSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $membership = CompanyUser::query()
            ->where('company_id', $company->id)
            ->where('user_id', $companyOwner->id)
            ->firstOrFail();
        $customerLink = SellerCustomer::query()
            ->where('seller_id', $seller->id)
            ->where('user_id', $customer->id)
            ->firstOrFail();

        $this->assertSame('Demo Company', $company->name);
        $this->assertSame('active', $company->status);
        $this->assertTrue($seller->company->is($company));
        $this->assertTrue($seller->owner->is($sellerOwner));
        $this->assertSame('owner', $membership->role);
        $this->assertSame('active', $membership->status);
        $this->assertSame('active', $customerLink->status);

        $account = PaymentProviderAccount::query()
            ->where('seller_id', $seller->id)
            ->where('provider', 'simulator')
            ->firstOrFail();

        $this->assertSame($company->id, $account->company_id);
        $this->assertSame($sellerOwner->id, $account->user_id);
        $this->assertSame('fake_demo_simulator_key_0000', $account->getCredentials()['api_key']);
        $this->assertStringNotContainsString('fake_demo_simulator_key_0000', (string) $account->getRawOriginal('encrypted_credentials'));
        $this->assertStringNotContainsString('fake_demo_simulator_key_0000', $account->getMaskedCredentials()['api_key']);
    }

    public function test_company_seller_seeder_is_idempotent_and_preserves_existing_user_password(): void
    {
        $existingPassword = Hash::make('existing-password');
        User::factory()->create([
            'email' => CompanySellerSeeder::SELLER_OWNER_EMAIL,
            'password' => $existingPassword,
        ]);

        $this->seed(CompanySellerSeeder::class);
        $this->seed(CompanySellerSeeder::class);

        $this->assertSame(1, Company::query()->where('slug', CompanySellerSeeder::COMPANY_SLUG)->count());
        $this->assertSame(1, Seller::query()->where('slug', CompanySellerSeeder::SELLER_SLUG)->count());
        $this->assertSame(1, CompanyUser::query()->count());
        $this->assertSame(1, SellerCustomer::query()->count());
        $this->assertSame(1, PaymentProviderAccount::query()->where('provider', 'simulator')->count());
        $this->assertSame(1, User::query()->where('email', CompanySellerSeeder::COMPANY_OWNER_EMAIL)->count());
        $this->assertSame(1, User::query()->where('email', CompanySellerSeeder::SELLER_OWNER_EMAIL)->count());
        $this->assertSame(1, User::query()->where('email', CompanySellerSeeder::CUSTOMER_EMAIL)->count());
        $this->assertSame($existingPassword, User::query()->where('email', CompanySellerSeeder::SELLER_OWNER_EMAIL)->value('password'));
    }

    public function test_database_seeder_includes_company_seller_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('companies', [
            'slug' => CompanySellerSeeder::COMPANY_SLUG,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('sellers', [
            'slug' => CompanySellerSeeder::SELLER_SLUG,
            'status' => 'active',
        ]);
    }
}
