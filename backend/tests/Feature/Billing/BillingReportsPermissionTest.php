<?php

namespace Tests\Feature\Billing;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\BillingPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingReportsPermissionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reports_permissions_are_seeded_and_split_financial_access(): void
    {
        $this->seed(BillingPermissionSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'name' => 'billing.reports.view',
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'billing.reports.view_financials',
        ]);

        $viewPermission = Permission::query()->where('name', 'billing.reports.view')->firstOrFail();
        $financialPermission = Permission::query()->where('name', 'billing.reports.view_financials')->firstOrFail();

        $viewer = User::factory()->create();
        $viewer->permissions()->sync([$viewPermission->id]);

        $financialViewer = User::factory()->create();
        $financialViewer->permissions()->sync([$viewPermission->id, $financialPermission->id]);

        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/billing/admin/reports/payment-status-summary')
            ->assertOk()
            ->assertJsonPath('data.scope', 'payment_status_summary');

        $this->getJson('/api/v1/billing/admin/reports/revenue-summary')
            ->assertForbidden();

        Sanctum::actingAs($financialViewer);
        $this->getJson('/api/v1/billing/admin/reports/revenue-summary')
            ->assertOk()
            ->assertJsonPath('data.scope', 'revenue_summary');

        $normalUser = User::factory()->create();

        Sanctum::actingAs($normalUser);
        $this->getJson('/api/v1/billing/admin/reports/payment-status-summary')
            ->assertForbidden();
        $this->getJson('/api/v1/billing/admin/reports/revenue-summary')
            ->assertForbidden();
    }
}
