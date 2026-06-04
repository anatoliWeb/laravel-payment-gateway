<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Seller;
use App\Models\SellerCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySellerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_seller_membership_and_customer_relations_work(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $customer = User::factory()->create();
        $seller = Seller::factory()->create([
            'company_id' => $company->id,
            'owner_user_id' => $owner->id,
        ]);
        $membership = CompanyUser::factory()->create([
            'company_id' => $company->id,
            'user_id' => $member->id,
            'role' => 'manager',
        ]);
        $customerLink = SellerCustomer::factory()->create([
            'seller_id' => $seller->id,
            'user_id' => $customer->id,
        ]);

        $this->assertTrue($company->sellers->first()->is($seller));
        $this->assertTrue($seller->company->is($company));
        $this->assertTrue($seller->owner->is($owner));
        $this->assertTrue($company->members->first()->is($membership));
        $this->assertTrue($company->users->first()->is($member));
        $this->assertTrue($seller->customerLinks->first()->is($customerLink));
        $this->assertTrue($seller->customers->first()->is($customer));
        $this->assertTrue($owner->ownedSellers->first()->is($seller));
        $this->assertTrue($member->companyMemberships->first()->is($membership));
        $this->assertTrue($customer->sellerCustomerLinks->first()->is($customerLink));
    }
}
