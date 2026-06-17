<?php

namespace Tests\Feature\Billing;

use App\Models\BillingRestriction;
use App\Models\FeatureOverride;
use App\Models\IdempotencyKey;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentProviderAccount;
use App\Models\Permission;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WebhookDelivery;
use Database\Seeders\BillingDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillingDemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_demo_billing_seeder_creates_stable_portfolio_dataset(): void
    {
        $this->seed(BillingDemoSeeder::class);
        $this->seed(BillingDemoSeeder::class);

        foreach ([
            BillingDemoSeeder::ADMIN_EMAIL,
            BillingDemoSeeder::OPERATOR_EMAIL,
            BillingDemoSeeder::NORMAL_EMAIL,
            BillingDemoSeeder::COMPANY_OWNER_EMAIL,
            BillingDemoSeeder::SELLER_OWNER_EMAIL,
            BillingDemoSeeder::PRIMARY_CUSTOMER_EMAIL,
            BillingDemoSeeder::CUSTOMER_ONE_EMAIL,
            BillingDemoSeeder::CUSTOMER_TWO_EMAIL,
            BillingDemoSeeder::CUSTOMER_THREE_EMAIL,
        ] as $email) {
            $this->assertDatabaseHas('users', ['email' => $email]);
        }

        $this->assertSame(6, Subscription::query()->count());
        $this->assertSame(3, Subscription::query()->whereIn('status', ['active', 'trialing'])->count());
        $this->assertSame(2, Subscription::query()->where('status', 'past_due')->count());
        $this->assertSame(1, Subscription::query()->where('status', 'cancelled')->count());

        $this->assertSame(6, Invoice::query()->count());
        $this->assertSame(3, Invoice::query()->where('status', Invoice::STATUS_PAID)->count());
        $this->assertSame(1, Invoice::query()->where('status', Invoice::STATUS_PAYMENT_PENDING)->count());
        $this->assertSame(1, Invoice::query()->where('status', Invoice::STATUS_OVERDUE)->count());
        $this->assertSame(1, Invoice::query()->where('status', Invoice::STATUS_FAILED)->count());

        $this->assertSame(9, Payment::query()->count());
        $this->assertSame(3, Payment::query()->where('status', 'succeeded')->count());
        $this->assertSame(1, Payment::query()->where('status', 'pending')->count());
        $this->assertSame(1, Payment::query()->where('status', 'processing')->count());
        $this->assertSame(2, Payment::query()->where('status', 'failed')->count());
        $this->assertSame(1, Payment::query()->where('status', 'expired')->count());
        $this->assertSame(1, Payment::query()->where('status', 'cancelled')->count());

        $wallet = Wallet::query()->where('uuid', 'demo-wallet-customer')->firstOrFail();
        $this->assertSame(2, $wallet->balances()->count());
        $this->assertSame(6, WalletTransaction::query()->count());

        $this->assertSame(4, PaymentProviderAccount::query()->where('provider', 'simulator')->count());
        $this->assertSame(4, WebhookDelivery::query()->count());
        $this->assertSame(4, IdempotencyKey::query()->count());
        $this->assertSame(3, BillingRestriction::query()->count());
        $this->assertSame(3, FeatureOverride::query()->count());

        foreach ([
            'billing.feature_overrides.view',
            'billing.provider_accounts.view',
            'billing.restrictions.create',
            'billing.reports.view',
        ] as $permissionName) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permissionName,
            ]);
        }

        $admin = User::query()->where('email', BillingDemoSeeder::ADMIN_EMAIL)->firstOrFail();
        $operator = User::query()->where('email', BillingDemoSeeder::OPERATOR_EMAIL)->firstOrFail();
        $normal = User::query()->where('email', BillingDemoSeeder::NORMAL_EMAIL)->firstOrFail();

        $adminPermissions = $admin->permissions()->pluck('name')->all();
        $operatorPermissions = $operator->permissions()->pluck('name')->all();
        $normalPermissions = $normal->permissions()->pluck('name')->all();

        $this->assertContains('billing.feature_overrides.view', $adminPermissions);
        $this->assertContains('billing.provider_accounts.view', $adminPermissions);
        $this->assertContains('billing.reports.view', $adminPermissions);
        $this->assertContains('billing.payments.view_any', $operatorPermissions);
        $this->assertNotContains('billing.restrictions.create', $operatorPermissions);
        $this->assertNotContains('billing.reports.view', $normalPermissions);
    }
}
