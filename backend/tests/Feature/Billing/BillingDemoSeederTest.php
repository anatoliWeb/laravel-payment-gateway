<?php

namespace Tests\Feature\Billing;

use App\Models\BillingRestriction;
use App\Models\FeatureOverride;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\PaymentProviderAccount;
use App\Models\Permission;
use App\Models\User;
use App\Models\Wallet;
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

        $this->assertDatabaseHas('users', [
            'email' => BillingDemoSeeder::ADMIN_EMAIL,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => BillingDemoSeeder::OPERATOR_EMAIL,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => BillingDemoSeeder::NORMAL_EMAIL,
        ]);

        $this->assertSame(1, Payment::query()->where('uuid', 'demo-payment-succeeded')->count());
        $this->assertSame(1, Wallet::query()->where('uuid', 'demo-wallet-customer')->count());
        $this->assertSame(1, PaymentProviderAccount::query()->where('uuid', 'demo-platform-simulator-account')->count());
        $this->assertSame(1, IdempotencyKey::query()->where('status', 'completed')->where('scope', 'payment.create')->count());
        $this->assertSame(1, BillingRestriction::query()->where('type', 'billing_blocked')->count());
        $this->assertSame(1, FeatureOverride::query()->where('feature_key', 'chat.messages.daily')->count());

        foreach ([
            'billing.feature_overrides.view',
            'billing.provider_accounts.view',
            'billing.restrictions.create',
        ] as $permissionName) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permissionName,
            ]);
        }

        $admin = User::query()->where('email', BillingDemoSeeder::ADMIN_EMAIL)->firstOrFail();
        $adminPermissions = $admin->permissions()->pluck('name')->all();
        $this->assertContains('billing.feature_overrides.view', $adminPermissions);
        $this->assertContains('billing.provider_accounts.view', $adminPermissions);
    }
}
