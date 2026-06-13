<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Seller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use Database\Seeders\BillingPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingReportsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_financial_report_endpoints_return_authoritative_aggregates(): void
    {
        Carbon::setTestNow('2026-06-13 12:00:00');
        $fixture = $this->createReportFixture();

        Sanctum::actingAs($fixture['financial_user']);

        $this->getJson('/api/v1/billing/admin/reports/revenue-summary?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope', 'revenue_summary')
            ->assertJsonPath('data.summary.payment_count', 4)
            ->assertJsonPath('data.summary.successful_payment_count', 2)
            ->assertJsonPath('data.summary.revenue_amount', 12000)
            ->assertJsonCount(2, 'data.currency_breakdown')
            ->assertJsonFragment(['currency' => 'USD'])
            ->assertJsonFragment(['currency' => 'EUR']);

        $this->getJson('/api/v1/billing/admin/reports/payment-status-summary?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.scope', 'payment_status_summary')
            ->assertJsonPath('data.summary.payment_count', 4)
            ->assertJsonFragment(['status' => 'succeeded', 'payment_count' => 2])
            ->assertJsonFragment(['status' => 'failed', 'payment_count' => 1])
            ->assertJsonFragment(['status' => 'pending', 'payment_count' => 1]);

        $this->getJson('/api/v1/billing/admin/reports/revenue-by-plan?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.scope', 'revenue_by_plan')
            ->assertJsonFragment(['plan_name' => 'Starter', 'revenue_amount' => 5000])
            ->assertJsonFragment(['plan_name' => 'Pro', 'revenue_amount' => 7000]);

        $this->getJson('/api/v1/billing/admin/reports/revenue-by-currency?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.scope', 'revenue_by_currency')
            ->assertJsonFragment(['currency' => 'USD', 'revenue_amount' => 5000])
            ->assertJsonFragment(['currency' => 'EUR', 'revenue_amount' => 7000]);

        $this->getJson('/api/v1/billing/admin/reports/revenue-by-seller-company?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.scope', 'revenue_by_seller_company')
            ->assertJsonFragment(['company_name' => 'Alpha Billing LLC', 'seller_name' => 'Alpha Merchant', 'revenue_amount' => 5000])
            ->assertJsonFragment(['company_name' => 'Beta Billing LLC', 'seller_name' => 'Beta Merchant', 'revenue_amount' => 7000]);
    }

    public function test_usage_report_endpoints_return_counts_and_currency_breakdowns(): void
    {
        Carbon::setTestNow('2026-06-13 12:00:00');
        $fixture = $this->createReportFixture();

        Sanctum::actingAs($fixture['financial_user']);

        $this->getJson('/api/v1/billing/admin/reports/subscription-metrics?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.scope', 'subscription_metrics')
            ->assertJsonPath('data.summary.subscription_count', 3)
            ->assertJsonPath('data.summary.active_subscription_count', 1)
            ->assertJsonPath('data.summary.trialing_subscription_count', 1)
            ->assertJsonPath('data.summary.past_due_subscription_count', 1)
            ->assertJsonPath('data.summary.cancelled_subscription_count', 0)
            ->assertJsonCount(2, 'data.plan_breakdown');

        $this->getJson('/api/v1/billing/admin/reports/invoice-metrics?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.scope', 'invoice_metrics')
            ->assertJsonPath('data.summary.invoice_count', 3)
            ->assertJsonPath('data.summary.paid_invoice_count', 1)
            ->assertJsonFragment(['status' => 'paid', 'invoice_count' => 1])
            ->assertJsonFragment(['currency' => 'EUR', 'paid_amount' => 7000]);

        $this->getJson('/api/v1/billing/admin/reports/wallet-metrics?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.scope', 'wallet_metrics')
            ->assertJsonPath('data.summary.wallet_count', 2)
            ->assertJsonPath('data.summary.transaction_count', 3)
            ->assertJsonFragment(['status' => 'active', 'wallet_count' => 1])
            ->assertJsonFragment(['currency' => 'USD', 'available_amount' => 12000])
            ->assertJsonFragment(['type' => 'top_up', 'transaction_count' => 1]);
    }

    public function test_invalid_report_filters_fail_validation_without_touching_database_state(): void
    {
        Carbon::setTestNow('2026-06-13 12:00:00');
        $fixture = $this->createReportFixture();

        Sanctum::actingAs($fixture['financial_user']);

        $this->getJson('/api/v1/billing/admin/reports/revenue-summary?date_from=2026-06-30&date_to=2026-06-01')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['date_to']);
    }

    /**
     * @return array{financial_user: User}
     */
    private function createReportFixture(): array
    {
        $this->seed(BillingPermissionSeeder::class);

        $viewPermission = Permission::query()->firstOrCreate(
            ['name' => 'billing.reports.view'],
            ['description' => 'View billing reports'],
        );
        $financialPermission = Permission::query()->firstOrCreate(
            ['name' => 'billing.reports.view_financials'],
            ['description' => 'View financial billing reports'],
        );

        $financialUser = User::factory()->create([
            'name' => 'Financial Reporter',
            'email' => 'financial.reporter@test.com',
        ]);
        $financialUser->permissions()->sync([$viewPermission->id, $financialPermission->id]);

        $companyAlpha = Company::factory()->create([
            'name' => 'Alpha Billing LLC',
            'slug' => 'alpha-billing',
        ]);
        $companyBeta = Company::factory()->create([
            'name' => 'Beta Billing LLC',
            'slug' => 'beta-billing',
        ]);

        $sellerAlpha = Seller::factory()->create([
            'company_id' => $companyAlpha->id,
            'name' => 'Alpha Merchant',
            'slug' => 'alpha-merchant',
        ]);
        $sellerBeta = Seller::factory()->create([
            'company_id' => $companyBeta->id,
            'name' => 'Beta Merchant',
            'slug' => 'beta-merchant',
        ]);

        $planStarter = Plan::factory()->basic()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price_amount' => 2900,
            'currency' => 'USD',
        ]);
        $planPro = Plan::factory()->pro()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price_amount' => 9900,
            'currency' => 'EUR',
        ]);

        $activeSubscription = Subscription::factory()->create([
            'user_id' => User::factory()->create()->id,
            'plan_id' => $planStarter->id,
            'status' => 'active',
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        $trialingSubscription = Subscription::factory()->create([
            'user_id' => User::factory()->create()->id,
            'plan_id' => $planPro->id,
            'status' => 'trialing',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);
        $pastDueSubscription = Subscription::factory()->create([
            'user_id' => User::factory()->create()->id,
            'plan_id' => $planPro->id,
            'status' => 'past_due',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        Payment::factory()->succeeded()->create([
            'user_id' => $activeSubscription->user_id,
            'payer_user_id' => $activeSubscription->user_id,
            'company_id' => $companyAlpha->id,
            'seller_id' => $sellerAlpha->id,
            'subscription_id' => $activeSubscription->id,
            'amount' => 5000,
            'currency' => 'USD',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);
        Payment::factory()->succeeded()->create([
            'user_id' => $trialingSubscription->user_id,
            'payer_user_id' => $trialingSubscription->user_id,
            'company_id' => $companyBeta->id,
            'seller_id' => $sellerBeta->id,
            'subscription_id' => $trialingSubscription->id,
            'amount' => 7000,
            'currency' => 'EUR',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        Payment::factory()->failed()->create([
            'user_id' => $pastDueSubscription->user_id,
            'payer_user_id' => $pastDueSubscription->user_id,
            'company_id' => $companyAlpha->id,
            'seller_id' => $sellerAlpha->id,
            'subscription_id' => $pastDueSubscription->id,
            'amount' => 3000,
            'currency' => 'USD',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        Payment::factory()->pending()->create([
            'user_id' => $pastDueSubscription->user_id,
            'payer_user_id' => $pastDueSubscription->user_id,
            'company_id' => null,
            'seller_id' => null,
            'subscription_id' => $pastDueSubscription->id,
            'amount' => 2000,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Invoice::factory()->issued()->create([
            'user_id' => $activeSubscription->user_id,
            'payer_user_id' => $activeSubscription->user_id,
            'company_id' => $companyAlpha->id,
            'seller_id' => $sellerAlpha->id,
            'subscription_id' => $activeSubscription->id,
            'currency' => 'USD',
            'total_amount' => 5000,
            'subtotal_amount' => 5000,
            'paid_amount' => 0,
            'due_amount' => 5000,
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);
        Invoice::factory()->paid()->create([
            'user_id' => $trialingSubscription->user_id,
            'payer_user_id' => $trialingSubscription->user_id,
            'company_id' => $companyBeta->id,
            'seller_id' => $sellerBeta->id,
            'subscription_id' => $trialingSubscription->id,
            'currency' => 'EUR',
            'total_amount' => 7000,
            'subtotal_amount' => 7000,
            'paid_amount' => 7000,
            'due_amount' => 0,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        Invoice::factory()->void()->create([
            'user_id' => $pastDueSubscription->user_id,
            'payer_user_id' => $pastDueSubscription->user_id,
            'company_id' => $companyAlpha->id,
            'seller_id' => $sellerAlpha->id,
            'subscription_id' => $pastDueSubscription->id,
            'currency' => 'USD',
            'total_amount' => 3000,
            'subtotal_amount' => 3000,
            'paid_amount' => 0,
            'due_amount' => 0,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $walletOwner = User::factory()->create([
            'name' => 'Wallet Holder',
            'email' => 'wallet.holder@test.com',
        ]);
        $wallet = Wallet::factory()->create([
            'user_id' => $walletOwner->id,
            'status' => 'active',
            'created_at' => now()->subDays(6),
            'updated_at' => now()->subDays(6),
        ]);
        $walletSecondary = Wallet::factory()->suspended()->create([
            'user_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        $usdCurrency = Currency::factory()->usd()->base()->create();
        $eurCurrency = Currency::factory()->eur()->create();

        WalletBalance::factory()->create([
            'wallet_id' => $wallet->id,
            'currency_id' => $usdCurrency->id,
            'available_amount' => 12000,
            'held_amount' => 500,
        ]);
        WalletBalance::factory()->create([
            'wallet_id' => $walletSecondary->id,
            'currency_id' => $eurCurrency->id,
            'available_amount' => 3000,
            'held_amount' => 0,
        ]);

        WalletTransaction::factory()->topUp()->create([
            'wallet_id' => $wallet->id,
            'currency_id' => $usdCurrency->id,
            'amount' => 5000,
            'direction' => 'credit',
            'type' => 'top_up',
            'status' => 'completed',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);
        WalletTransaction::factory()->debit()->create([
            'wallet_id' => $wallet->id,
            'currency_id' => $usdCurrency->id,
            'amount' => 2000,
            'direction' => 'debit',
            'type' => 'debit',
            'status' => 'completed',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        WalletTransaction::factory()->refund()->create([
            'wallet_id' => $walletSecondary->id,
            'currency_id' => $eurCurrency->id,
            'amount' => 1000,
            'direction' => 'credit',
            'type' => 'refund',
            'status' => 'completed',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        return [
            'financial_user' => $financialUser,
        ];
    }
}
