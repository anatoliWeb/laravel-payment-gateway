<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\BillingPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invoice_api_creates_issues_and_creates_payment_for_invoice(): void
    {
        $user = $this->actingUserWithPermissions([
            'billing.invoices.create',
            'billing.invoices.manage',
        ]);
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);

        $create = $this->withHeader('Idempotency-Key', 'invoice-api-create-1')
            ->postJson('/api/v1/billing/invoices', [
                'currency' => 'USD',
                'description' => 'API invoice',
                'items' => [
                    ['description' => 'Plan', 'quantity' => 1, 'unit_amount' => 2900],
                ],
            ])->assertCreated()
            ->assertJsonPath('data.status', Invoice::STATUS_DRAFT)
            ->assertJsonPath('data.total_amount', 2900);

        $invoiceId = $create->json('data.id');

        $this->postJson("/api/v1/billing/invoices/{$invoiceId}/issue")
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_ISSUED);

        $this->withHeader('Idempotency-Key', 'invoice-api-pay-1')
            ->postJson("/api/v1/billing/invoices/{$invoiceId}/pay", [
                'payment_source' => 'payment_method',
            ])->assertCreated()
            ->assertJsonPath('data.amount', 2900)
            ->assertJsonPath('data.invoice_id', $invoiceId);

        $this->assertSame(Invoice::STATUS_PAYMENT_PENDING, Invoice::query()->findOrFail($invoiceId)->status);
    }

    public function test_user_can_view_own_invoice_but_not_another_users_invoice(): void
    {
        $user = User::factory()->create();
        $own = Invoice::factory()->create(['user_id' => $user->id, 'payer_user_id' => $user->id]);
        $other = Invoice::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/billing/invoices/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $own->id);

        $this->getJson("/api/v1/billing/invoices/{$other->id}")
            ->assertStatus(404)
            ->assertJsonPath('errors.code', 'invoice_not_found');
    }

    private function actingUserWithPermissions(array $permissions): User
    {
        $this->seed(BillingPermissionSeeder::class);
        $user = User::factory()->create();

        $permissionIds = Permission::query()
            ->whereIn('name', $permissions)
            ->pluck('id')
            ->all();
        $user->permissions()->syncWithoutDetaching($permissionIds);

        Sanctum::actingAs($user);

        return $user;
    }
}
