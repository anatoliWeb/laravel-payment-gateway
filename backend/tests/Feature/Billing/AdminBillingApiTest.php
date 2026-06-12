<?php

namespace Tests\Feature\Billing;

use App\Models\Payment;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\BillingPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBillingApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_list_payments_with_admin_billing_endpoint(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(BillingPermissionSeeder::class);

        $payment = Payment::factory()->processing()->create();
        Sanctum::actingAs(User::query()->where('email', 'admin@test.com')->firstOrFail());

        $this->getJson('/api/v1/billing/admin/payments')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'uuid' => $payment->uuid,
            ]);
    }

    public function test_payment_simulation_accepts_uuid_route_binding(): void
    {
        $actor = User::factory()->create();
        $permission = Permission::query()->firstOrCreate(
            ['name' => 'billing.payments.simulate'],
            ['description' => 'Simulate payment outcomes'],
        );
        $actor->permissions()->syncWithoutDetaching([$permission->id]);

        $payment = Payment::factory()->processing()->create();
        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/billing/payments/{$payment->uuid}/simulate/success")
            ->assertOk()
            ->assertJsonPath('data.uuid', $payment->uuid)
            ->assertJsonPath('data.status', 'succeeded');

        $this->assertSame('succeeded', $payment->refresh()->status);
    }
}
