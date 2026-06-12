<?php

namespace Database\Seeders;

use App\Models\BillingRestriction;
use App\Models\Company;
use App\Models\FeatureOverride;
use App\Models\IdempotencyKey;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Payment;
use App\Models\PaymentProviderAccount;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Seller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use App\Models\WebhookDelivery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds a stable, portfolio-safe billing dataset for admin/operator review.
 *
 * WHY:
 * The demo UI needs realistic history rows, wallet balances, webhooks,
 * restrictions, overrides, and idempotency records without touching real
 * provider systems or rewriting runtime billing logic.
 */
class BillingDemoSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'demo-admin@example.com';

    public const OPERATOR_EMAIL = 'demo-operator@example.com';

    public const NORMAL_EMAIL = 'demo-normal@example.com';

    public const USER_SEED_KEY = 'billing_demo_users_v1';

    public const DATA_SEED_KEY = 'billing_demo_data_v1';

    public function run(): void
    {
        $this->call([
            BillingPermissionSeeder::class,
            CurrencySeeder::class,
            BillingSeeder::class,
            CompanySellerSeeder::class,
        ]);

        $now = now();
        $admin = $this->firstOrCreateDemoUser(self::ADMIN_EMAIL, 'Demo Billing Admin');
        $operator = $this->firstOrCreateDemoUser(self::OPERATOR_EMAIL, 'Demo Billing Operator');
        $normal = $this->firstOrCreateDemoUser(self::NORMAL_EMAIL, 'Demo Billing User');

        $this->attachBillingPermissions($admin);
        $this->attachReadOnlyBillingPermissions($operator);

        $company = Company::query()->where('slug', CompanySellerSeeder::COMPANY_SLUG)->firstOrFail();
        $seller = Seller::query()->where('slug', CompanySellerSeeder::SELLER_SLUG)->firstOrFail();
        $customer = User::query()->where('email', CompanySellerSeeder::CUSTOMER_EMAIL)->firstOrFail();

        $usdPlan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $basicPlan = Plan::query()->where('slug', 'basic')->firstOrFail();
        $enterprisePlan = Plan::query()->where('slug', 'enterprise')->firstOrFail();

        $activeSubscription = $this->upsertSubscription([
            'uuid' => 'demo-subscription-active',
            'user_id' => $customer->id,
            'plan_id' => $usdPlan->id,
            'status' => 'active',
            'started_at' => $now->copy()->subDays(12),
            'current_period_start' => $now->copy()->startOfMonth(),
            'current_period_end' => $now->copy()->endOfMonth(),
            'cancel_at_period_end' => false,
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
                'purpose' => 'active_subscription_demo',
            ],
        ]);

        $pendingSubscription = $this->upsertSubscription([
            'uuid' => 'demo-subscription-pending',
            'user_id' => $operator->id,
            'plan_id' => $basicPlan->id,
            'status' => 'pending',
            'started_at' => null,
            'current_period_start' => null,
            'current_period_end' => null,
            'cancel_at_period_end' => false,
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
                'purpose' => 'pending_subscription_demo',
            ],
        ]);

        $pastDueSubscription = $this->upsertSubscription([
            'uuid' => 'demo-subscription-past-due',
            'user_id' => $customer->id,
            'plan_id' => $enterprisePlan->id,
            'status' => 'past_due',
            'started_at' => $now->copy()->subMonths(2),
            'current_period_start' => $now->copy()->subMonth(),
            'current_period_end' => $now->copy()->subDay(),
            'cancel_at_period_end' => false,
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
                'purpose' => 'past_due_subscription_demo',
            ],
        ]);

        $wallet = $this->upsertWallet($customer->id, 'demo-wallet-customer', 'active');
        $usdCurrencyId = DB::table('currencies')->where('code', 'USD')->value('id');
        $eurCurrencyId = DB::table('currencies')->where('code', 'EUR')->value('id');
        if ($usdCurrencyId) {
            $this->upsertWalletBalance($wallet->id, $usdCurrencyId, 25000, 2000);
        }
        if ($eurCurrencyId) {
            $this->upsertWalletBalance($wallet->id, $eurCurrencyId, 8000, 0);
        }

        $providerAccount = PaymentProviderAccount::query()->firstOrNew([
            'uuid' => 'demo-platform-simulator-account',
        ]);
        $providerAccount->fill([
            'user_id' => $admin->id,
            'company_id' => $company->id,
            'seller_id' => null,
            'provider' => 'simulator',
            'display_name' => 'Demo Platform Simulator',
            'status' => 'active',
            'mode' => 'test',
            'config_source' => 'database',
            'public_config' => [
                'seeded' => true,
                'demo' => true,
            ],
            'capabilities' => [
                'charge' => true,
                'refund' => true,
                'webhook_verification' => true,
            ],
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
                'purpose' => 'platform_simulator_account',
            ],
        ]);
        $providerAccount->setCredentials([
            'api_key' => 'fake_platform_simulator_key_0000',
        ]);
        $providerAccount->save();

        $issuedInvoice = $this->upsertInvoice([
            'uuid' => 'demo-invoice-issued',
            'number' => 'INV-DEMO-0001',
            'user_id' => $customer->id,
            'payer_user_id' => $customer->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'subscription_id' => $activeSubscription->id,
            'payment_id' => null,
            'status' => Invoice::STATUS_ISSUED,
            'currency' => 'USD',
            'subtotal_amount' => 10000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 10000,
            'paid_amount' => 0,
            'due_amount' => 10000,
            'issued_at' => $now->copy()->subDays(2),
            'due_at' => $now->copy()->addDays(12),
            'paid_at' => null,
            'voided_at' => null,
            'overdue_at' => null,
            'description' => 'Demo billed services',
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
            ],
            'ownership_metadata' => [
                'scope' => 'company',
            ],
        ]);

        $paymentSucceeded = $this->upsertPayment([
            'uuid' => 'demo-payment-succeeded',
            'user_id' => $customer->id,
            'payer_user_id' => $customer->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'provider_account_id' => $providerAccount->id,
            'subscription_id' => $activeSubscription->id,
            'invoice_id' => $issuedInvoice->id,
            'parent_payment_id' => null,
            'amount' => 10000,
            'currency' => 'USD',
            'status' => 'succeeded',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-prov-succeeded',
            'description' => 'Demo succeeded payment',
            'failure_reason' => null,
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
                'payment_source' => 'payment_method',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
                'company_id' => $company->id,
                'seller_id' => $seller->id,
            ],
            'paid_at' => $now->copy()->subDay(),
            'failed_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
        ]);

        $paymentPending = $this->upsertPayment([
            'uuid' => 'demo-payment-pending',
            'user_id' => $normal->id,
            'payer_user_id' => $normal->id,
            'company_id' => null,
            'seller_id' => null,
            'provider_account_id' => $providerAccount->id,
            'subscription_id' => $pendingSubscription->id,
            'invoice_id' => null,
            'parent_payment_id' => null,
            'amount' => 2900,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-prov-pending',
            'description' => 'Demo pending payment',
            'failure_reason' => null,
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
                'payment_source' => 'wallet_first',
            ],
            'ownership_metadata' => [
                'scope' => 'user',
            ],
            'paid_at' => null,
            'failed_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
        ]);

        $paymentFailed = $this->upsertPayment([
            'uuid' => 'demo-payment-failed',
            'user_id' => $customer->id,
            'payer_user_id' => $customer->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'provider_account_id' => $providerAccount->id,
            'subscription_id' => $pastDueSubscription->id,
            'invoice_id' => null,
            'parent_payment_id' => null,
            'amount' => 49900,
            'currency' => 'USD',
            'status' => 'failed',
            'payment_method' => 'fake_card',
            'provider' => 'simulator',
            'provider_reference' => 'demo-prov-failed',
            'description' => 'Demo failed payment',
            'failure_reason' => 'card_declined',
            'callback_url' => 'https://example.test/billing/callback',
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
                'payment_source' => 'payment_method',
            ],
            'ownership_metadata' => [
                'scope' => 'company',
                'company_id' => $company->id,
                'seller_id' => $seller->id,
            ],
            'paid_at' => null,
            'failed_at' => $now->copy()->subHours(2),
            'expired_at' => null,
            'cancelled_at' => null,
        ]);

        $this->upsertInvoice([
            'uuid' => 'demo-invoice-payment-pending',
            'number' => 'INV-DEMO-0002',
            'user_id' => $normal->id,
            'payer_user_id' => $normal->id,
            'company_id' => null,
            'seller_id' => null,
            'subscription_id' => $pendingSubscription->id,
            'payment_id' => $paymentPending->id,
            'status' => Invoice::STATUS_PAYMENT_PENDING,
            'currency' => 'USD',
            'subtotal_amount' => 2900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 2900,
            'paid_amount' => 0,
            'due_amount' => 2900,
            'issued_at' => $now->copy()->subDay(),
            'due_at' => $now->copy()->addDays(7),
            'paid_at' => null,
            'voided_at' => null,
            'overdue_at' => null,
            'description' => 'Pending invoice demo',
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
            ],
            'ownership_metadata' => [
                'scope' => 'user',
            ],
        ]);

        $this->upsertInvoice([
            'uuid' => 'demo-invoice-paid',
            'number' => 'INV-DEMO-0003',
            'user_id' => $customer->id,
            'payer_user_id' => $customer->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'subscription_id' => $activeSubscription->id,
            'payment_id' => $paymentSucceeded->id,
            'status' => Invoice::STATUS_PAID,
            'currency' => 'USD',
            'subtotal_amount' => 10000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 10000,
            'paid_amount' => 10000,
            'due_amount' => 0,
            'issued_at' => $now->copy()->subDays(4),
            'due_at' => $now->copy()->subDay(),
            'paid_at' => $now->copy()->subDay(),
            'voided_at' => null,
            'overdue_at' => null,
            'description' => 'Paid invoice demo',
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
            ],
            'ownership_metadata' => [
                'scope' => 'company',
            ],
        ]);

        $this->upsertInvoiceItem($issuedInvoice->id, 'monthly_plan', 'Pro monthly plan', 1, 10000);

        $this->upsertPaymentTransaction([
            'payment_id' => $paymentSucceeded->id,
            'type' => 'payment_created',
            'status_from' => null,
            'status_to' => 'pending',
            'amount' => 10000,
            'currency' => 'USD',
            'message' => 'Demo payment created.',
            'payload' => [
                'source' => 'billing_demo_seeder',
                'seed_key' => self::DATA_SEED_KEY,
            ],
        ]);
        $this->upsertPaymentTransaction([
            'payment_id' => $paymentSucceeded->id,
            'type' => 'payment_succeeded',
            'status_from' => 'processing',
            'status_to' => 'succeeded',
            'amount' => 10000,
            'currency' => 'USD',
            'message' => 'Demo payment succeeded.',
            'payload' => [
                'source' => 'billing_demo_seeder',
                'seed_key' => self::DATA_SEED_KEY,
            ],
        ]);
        $this->upsertPaymentTransaction([
            'payment_id' => $paymentFailed->id,
            'type' => 'payment_failed',
            'status_from' => 'processing',
            'status_to' => 'failed',
            'amount' => 49900,
            'currency' => 'USD',
            'message' => 'Demo payment failed.',
            'payload' => [
                'source' => 'billing_demo_seeder',
                'seed_key' => self::DATA_SEED_KEY,
            ],
        ]);

        $this->upsertWebhookDelivery($paymentSucceeded->id, $activeSubscription->id, $issuedInvoice->id, 'payment.succeeded', 'delivered', 1, 200, 'ok');
        $this->upsertWebhookDelivery($paymentFailed->id, $pastDueSubscription->id, null, 'payment.failed', 'failed', 2, 500, 'gateway timeout');
        $this->upsertWebhookDelivery($paymentPending->id, $pendingSubscription->id, null, 'payment.pending', 'permanently_failed', 3, 500, 'permanent_failure');

        $this->upsertIdempotencyKey($customer->id, 'payment.create', 'POST', '/api/v1/billing/payments', 'completed', 201, $paymentSucceeded);
        $this->upsertIdempotencyKey($normal->id, 'wallet.adjustment', 'POST', '/api/v1/billing/wallet-adjustments', 'processing', null, null);

        $this->upsertRestriction($customer->id, 'billing_blocked', 'billing', null, 'Manual billing review demo', $admin->id, true);
        $this->upsertRestriction($customer->id, 'payment_blocked', 'payment', null, 'Payment blocked for demo review', $admin->id, true);
        $this->upsertRestriction($normal->id, 'feature_blocked', 'feature', 'chat.messages.daily', 'Temporary feature block demo', $admin->id, true);

        $this->upsertFeatureOverride($customer->id, null, 'chat.messages.daily', '5000', 'integer', 'daily', 'calendar_day', 100, 'Demo chat limit uplift', $admin->id, true);
        $this->upsertFeatureOverride($normal->id, $pendingSubscription->id, 'dialer.concurrent_calls', '3', 'integer', 'none', 'none', 120, 'Demo subscription override', $admin->id, true);
    }

    private function firstOrCreateDemoUser(string $email, string $name): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'password' => Hash::make('password'),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);
        $user->save();

        return $user;
    }

    private function attachBillingPermissions(User $user): void
    {
        $adminRole = Role::query()->where('name', 'admin')->first();
        if ($adminRole) {
            $user->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        $permissionIds = Permission::query()
            ->whereIn('name', array_keys(BillingPermissionSeeder::PERMISSIONS))
            ->pluck('id')
            ->all();

        $user->permissions()->syncWithoutDetaching($permissionIds);
    }

    private function attachReadOnlyBillingPermissions(User $user): void
    {
        $permissionIds = Permission::query()
            ->whereIn('name', [
                'billing.payments.view_any',
                'billing.payments.view_transactions',
                'billing.subscriptions.view_any',
                'billing.wallets.view_any',
                'billing.idempotency.view_any',
                'billing.provider_accounts.view_any',
                'billing.restrictions.view_any',
                'billing.overrides.view_any',
                'billing.webhooks.view_any',
            ])
            ->pluck('id')
            ->all();

        $user->permissions()->syncWithoutDetaching($permissionIds);
    }

    private function upsertSubscription(array $attributes): Subscription
    {
        $subscription = Subscription::query()->firstOrNew(['uuid' => $attributes['uuid']]);
        $subscription->fill(array_merge($attributes, [
            'metadata' => array_merge((array) ($attributes['metadata'] ?? []), [
                'seeded' => true,
            ]),
        ]));
        $subscription->save();

        return $subscription;
    }

    private function upsertWallet(int $userId, string $uuid, string $status): Wallet
    {
        $wallet = Wallet::query()->firstOrNew(['uuid' => $uuid]);
        $wallet->fill([
            'user_id' => $userId,
            'status' => $status,
            'metadata' => [
                'seeded' => true,
                'seed_key' => self::DATA_SEED_KEY,
            ],
        ]);
        $wallet->save();

        return $wallet;
    }

    private function upsertWalletBalance(int $walletId, int $currencyId, int $availableAmount, int $heldAmount): void
    {
        WalletBalance::query()->updateOrCreate(
            [
                'wallet_id' => $walletId,
                'currency_id' => $currencyId,
            ],
            [
                'available_amount' => $availableAmount,
                'held_amount' => $heldAmount,
                'metadata' => [
                    'seeded' => true,
                    'seed_key' => self::DATA_SEED_KEY,
                ],
            ],
        );
    }

    private function upsertInvoice(array $attributes): Invoice
    {
        $invoice = Invoice::query()->firstOrNew(['uuid' => $attributes['uuid']]);
        $invoice->fill(array_merge($attributes, [
            'metadata' => array_merge((array) ($attributes['metadata'] ?? []), [
                'seeded' => true,
            ]),
        ]));
        $invoice->save();

        return $invoice;
    }

    private function upsertInvoiceItem(int $invoiceId, string $type, string $description, int $quantity, int $unitAmount): void
    {
        $subtotalAmount = $quantity * $unitAmount;

        InvoiceItem::query()->updateOrCreate(
            [
                'invoice_id' => $invoiceId,
                'description' => $description,
            ],
            [
                'item_type' => $type,
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'subtotal_amount' => $subtotalAmount,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $subtotalAmount,
                'metadata' => [
                    'seeded' => true,
                    'seed_key' => self::DATA_SEED_KEY,
                ],
            ],
        );
    }

    private function upsertPayment(array $attributes): Payment
    {
        $payment = Payment::query()->firstOrNew(['uuid' => $attributes['uuid']]);
        $payment->fill(array_merge($attributes, [
            'metadata' => array_merge((array) ($attributes['metadata'] ?? []), [
                'seeded' => true,
            ]),
            'ownership_metadata' => array_merge((array) ($attributes['ownership_metadata'] ?? []), [
                'seeded' => true,
            ]),
        ]));
        $payment->save();

        return $payment;
    }

    private function upsertPaymentTransaction(array $attributes): void
    {
        PaymentTransaction::query()->updateOrCreate(
            [
                'payment_id' => $attributes['payment_id'],
                'type' => $attributes['type'],
                'status_to' => $attributes['status_to'],
            ],
            [
                'status_from' => $attributes['status_from'],
                'amount' => $attributes['amount'],
                'currency' => $attributes['currency'],
                'message' => $attributes['message'],
                'payload' => $attributes['payload'],
            ],
        );
    }

    private function upsertWebhookDelivery(int $paymentId, int $subscriptionId, ?int $invoiceId, string $event, string $status, int $attempts, int $statusCode, string $responseBody): void
    {
        WebhookDelivery::query()->updateOrCreate(
            [
                'payment_id' => $paymentId,
                'event' => $event,
                'status' => $status,
            ],
            [
                'uuid' => 'demo-wh-'.substr(hash('sha256', $event.'|'.$status), 0, 24),
                'subscription_id' => $subscriptionId,
                'invoice_id' => $invoiceId,
                'url' => 'https://example.test/webhooks/billing',
                'payload' => [
                    'source' => 'billing_demo_seeder',
                    'seed_key' => self::DATA_SEED_KEY,
                ],
                'response_status' => $statusCode,
                'response_body' => $responseBody,
                'attempts' => $attempts,
                'max_attempts' => 5,
                'next_retry_at' => $status === 'failed' ? now()->addMinutes(15) : null,
                'last_attempt_at' => now(),
                'delivered_at' => $status === 'delivered' ? now() : null,
                'failed_at' => in_array($status, ['failed', 'permanently_failed'], true) ? now() : null,
                'metadata' => [
                    'seeded' => true,
                    'seed_key' => self::DATA_SEED_KEY,
                ],
            ],
        );
    }

    private function upsertIdempotencyKey(int $userId, string $scope, string $method, string $endpoint, string $status, ?int $responseStatus, ?Payment $payment): void
    {
        IdempotencyKey::query()->updateOrCreate(
            [
                'key' => hash('sha256', "{$scope}:{$method}:{$endpoint}:{$userId}"),
            ],
            [
                'user_id' => $userId,
                'scope' => $scope,
                'method' => $method,
                'endpoint' => $endpoint,
                'request_hash' => hash('sha256', "{$scope}:{$method}:{$endpoint}:{$userId}:request"),
                'response_body' => $payment ? [
                    'payment_uuid' => $payment->uuid,
                    'seeded' => true,
                ] : [
                    'seeded' => true,
                ],
                'response_status' => $responseStatus,
                'related_type' => $payment ? Payment::class : null,
                'related_id' => $payment?->id,
                'status' => $status,
                'locked_until' => $status === 'processing' ? now()->addMinutes(5) : null,
                'expires_at' => now()->addDay(),
            ],
        );
    }

    private function upsertRestriction(int $userId, string $type, string $scope, ?string $featureKey, string $reason, int $createdBy, bool $active): void
    {
        BillingRestriction::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'type' => $type,
                'scope' => $scope,
                'feature_key' => $featureKey,
            ],
            [
                'reason' => $reason,
                'is_active' => $active,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'created_by' => $createdBy,
                'metadata' => [
                    'seeded' => true,
                    'seed_key' => self::DATA_SEED_KEY,
                ],
            ],
        );
    }

    private function upsertFeatureOverride(
        int $userId,
        ?int $subscriptionId,
        string $featureKey,
        string $value,
        string $valueType,
        string $period,
        string $resetPolicy,
        int $priority,
        string $reason,
        int $createdBy,
        bool $enabled,
    ): void {
        FeatureOverride::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'feature_key' => $featureKey,
            ],
            [
                'value' => $value,
                'value_type' => $valueType,
                'period' => $period,
                'reset_policy' => $resetPolicy,
                'is_enabled' => $enabled,
                'priority' => $priority,
                'reason' => $reason,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'created_by' => $createdBy,
                'metadata' => [
                    'seeded' => true,
                    'seed_key' => self::DATA_SEED_KEY,
                ],
            ],
        );
    }
}
