<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\User;
use App\Services\Billing\OwnershipScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OwnershipScopeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_scoped_payment_resolves_without_company_or_seller(): void
    {
        $payer = User::factory()->create();

        $scope = app(OwnershipScopeService::class)->resolveForPayment($payer);

        $this->assertSame($payer->id, $scope['payer_user_id']);
        $this->assertNull($scope['company_id']);
        $this->assertNull($scope['seller_id']);
        $this->assertSame('user', $scope['ownership_metadata']['scope']);
    }

    public function test_seller_scope_infers_company_and_rejects_conflicting_company(): void
    {
        $payer = User::factory()->create();
        $seller = Seller::factory()->create();

        $scope = app(OwnershipScopeService::class)->resolveForPayment($payer, [
            'seller_id' => $seller->id,
        ]);

        $this->assertSame($seller->id, $scope['seller_id']);
        $this->assertSame($seller->company_id, $scope['company_id']);
        $this->assertSame('seller', $scope['ownership_metadata']['scope']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payment_ownership_scope_conflict');

        app(OwnershipScopeService::class)->resolveForPayment($payer, [
            'seller_id' => $seller->id,
            'company_id' => Company::factory()->create()->id,
        ]);
    }

    public function test_seller_owner_and_company_member_can_access_their_scopes(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $seller = Seller::factory()->create([
            'company_id' => $company->id,
            'owner_user_id' => $owner->id,
        ]);
        CompanyUser::factory()->create([
            'company_id' => $company->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);
        $service = app(OwnershipScopeService::class);

        $this->assertTrue($service->canActorAccessSeller($owner, $seller));
        $this->assertTrue($service->canActorAccessCompany($member, $company));
        $this->assertTrue($service->canActorAccessSeller($member, $seller));
    }

    public function test_payer_can_access_own_payment_and_unrelated_user_cannot_access_scoped_payment(): void
    {
        $payer = User::factory()->create();
        $unrelated = User::factory()->create();
        $seller = Seller::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $payer->id,
            'payer_user_id' => $payer->id,
            'company_id' => $seller->company_id,
            'seller_id' => $seller->id,
        ]);
        $service = app(OwnershipScopeService::class);

        $this->assertTrue($service->canActorAccessPayment($payer, $payment));
        $this->assertFalse($service->canActorAccessCompany($unrelated, $seller->company));
        $this->assertFalse($service->canActorAccessSeller($unrelated, $seller));
        $this->assertFalse($service->canActorAccessPayment($unrelated, $payment));
    }
}
