<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentProviderAccount;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentOwnershipScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_can_be_created_without_company_or_seller_and_stores_explicit_payer(): void
    {
        $payer = $this->actingPayer();
        $this->activeCurrency();
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $payer->id]);

        $response = $this->withHeader('Idempotency-Key', 'ownership-user-payment')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1500,
                'currency' => 'USD',
                'payment_source' => 'payment_method',
            ])->assertCreated();

        $payment = Payment::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

        $this->assertSame($payer->id, $payment->user_id);
        $this->assertSame($payer->id, $payment->payer_user_id);
        $this->assertNull($payment->company_id);
        $this->assertNull($payment->seller_id);
        $this->assertSame('user', $payment->ownership_metadata['scope']);
    }

    public function test_payment_with_seller_infers_company_and_uses_seller_provider_account(): void
    {
        $payer = $this->actingPayer();
        $this->activeCurrency();
        $seller = Seller::factory()->create();
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $payer->id]);
        $sellerProviderAccount = PaymentProviderAccount::factory()->create([
            'user_id' => $seller->owner_user_id,
            'company_id' => $seller->company_id,
            'seller_id' => $seller->id,
            'provider' => 'simulator',
        ]);

        $response = $this->withHeader('Idempotency-Key', 'ownership-seller-payment')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1500,
                'currency' => 'USD',
                'payment_source' => 'payment_method',
                'seller_id' => $seller->id,
            ])->assertCreated();

        $payment = Payment::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

        $this->assertSame($payer->id, $payment->payer_user_id);
        $this->assertSame($seller->id, $payment->seller_id);
        $this->assertSame($seller->company_id, $payment->company_id);
        $this->assertSame($sellerProviderAccount->id, $payment->provider_account_id);
        $this->assertSame('seller', $payment->ownership_metadata['scope']);
    }

    public function test_payment_rejects_conflicting_company_and_seller_scope(): void
    {
        $payer = $this->actingPayer();
        $this->activeCurrency();
        $seller = Seller::factory()->create();
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $payer->id]);

        $this->withHeader('Idempotency-Key', 'ownership-scope-conflict')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1500,
                'currency' => 'USD',
                'payment_source' => 'payment_method',
                'seller_id' => $seller->id,
                'company_id' => Seller::factory()->create()->company_id,
            ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_ownership_scope_conflict');

        $this->assertSame(0, Payment::query()->count());
    }

    private function actingPayer(): User
    {
        $payer = User::factory()->create();
        Sanctum::actingAs($payer);

        return $payer;
    }

    private function activeCurrency(): void
    {
        Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_active' => true,
            'is_base' => true,
        ]);
    }
}
