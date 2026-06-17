<?php

namespace Database\Seeders\Billing;

use App\Models\BillingRestriction;
use App\Models\Company;
use App\Models\CompanyUser;
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
use App\Models\SellerCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use App\Models\WebhookDelivery;
use Database\Seeders\BillingPermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

abstract class BillingDemoSeederSupport extends Seeder
{
    public const ADMIN_EMAIL = 'demo-admin@example.com';

    public const OPERATOR_EMAIL = 'demo-operator@example.com';

    public const NORMAL_EMAIL = 'demo-normal@example.com';

    public const COMPANY_OWNER_EMAIL = 'demo-company-owner@example.com';

    public const SELLER_OWNER_EMAIL = 'demo-seller-owner@example.com';

    public const PRIMARY_CUSTOMER_EMAIL = 'demo-customer@example.com';

    public const CUSTOMER_ONE_EMAIL = 'demo-customer-01@example.com';

    public const CUSTOMER_TWO_EMAIL = 'demo-customer-02@example.com';

    public const CUSTOMER_THREE_EMAIL = 'demo-customer-03@example.com';

    public const COMPANY_SLUG = 'demo-company';

    public const SELLER_SLUG = 'demo-seller';

    public const USER_SEED_KEY = 'billing_demo_users_v2';

    public const DATA_SEED_KEY = 'billing_demo_data_v2';

    protected function seedMetadata(array $extra = []): array
    {
        return array_merge([
            'seeded' => true,
            'seed_key' => self::DATA_SEED_KEY,
        ], $extra);
    }

    protected function firstOrCreateDemoUser(string $email, string $name): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'password' => Hash::make('password'),
        ]);
        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);
        $user->save();

        return $user;
    }

    protected function demoCompany(): Company
    {
        return Company::query()->where('slug', self::COMPANY_SLUG)->firstOrFail();
    }

    protected function demoSeller(): Seller
    {
        return Seller::query()->where('slug', self::SELLER_SLUG)->firstOrFail();
    }

    protected function demoPlan(string $slug): Plan
    {
        return Plan::query()->where('slug', $slug)->firstOrFail();
    }

    protected function demoUser(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    protected function demoCurrencyId(string $code): int
    {
        return (int) DB::table('currencies')->where('code', $code)->value('id');
    }

    protected function attachBillingPermissions(User $user): void
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

    protected function attachReadOnlyBillingPermissions(User $user): void
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
                'billing.reports.view',
            ])
            ->pluck('id')
            ->all();

        $user->permissions()->syncWithoutDetaching($permissionIds);
    }

    protected function upsertCompanyUser(int $companyId, int $userId, string $role = 'member', string $status = 'active', array $metadata = []): void
    {
        CompanyUser::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => $userId,
            ],
            [
                'role' => $role,
                'status' => $status,
                'metadata' => $this->seedMetadata($metadata),
            ],
        );
    }

    protected function upsertSellerCustomer(int $sellerId, int $userId, string $status = 'active', array $metadata = []): void
    {
        SellerCustomer::query()->updateOrCreate(
            [
                'seller_id' => $sellerId,
                'user_id' => $userId,
            ],
            [
                'status' => $status,
                'metadata' => $this->seedMetadata($metadata),
            ],
        );
    }

    protected function upsertSubscription(array $attributes): Subscription
    {
        $subscription = Subscription::query()->firstOrNew(['uuid' => $attributes['uuid']]);
        $subscription->fill(array_merge($attributes, [
            'metadata' => $this->seedMetadata((array) ($attributes['metadata'] ?? [])),
        ]));
        $subscription->save();

        return $subscription;
    }

    protected function upsertWallet(int $userId, string $uuid, string $status): Wallet
    {
        $wallet = Wallet::query()->firstOrNew(['uuid' => $uuid]);
        $wallet->fill([
            'user_id' => $userId,
            'status' => $status,
            'metadata' => $this->seedMetadata(),
        ]);
        $wallet->save();

        return $wallet;
    }

    protected function upsertWalletBalance(int $walletId, int $currencyId, int $availableAmount, int $heldAmount): WalletBalance
    {
        return WalletBalance::query()->updateOrCreate(
            [
                'wallet_id' => $walletId,
                'currency_id' => $currencyId,
            ],
            [
                'available_amount' => $availableAmount,
                'held_amount' => $heldAmount,
                'metadata' => $this->seedMetadata(),
            ],
        );
    }

    protected function upsertWalletTransaction(array $attributes): WalletTransaction
    {
        $walletTransaction = WalletTransaction::query()->firstOrNew([
            'wallet_id' => $attributes['wallet_id'],
            'idempotency_key' => $attributes['idempotency_key'],
        ]);
        $walletTransaction->fill(array_merge($attributes, [
            'metadata' => $this->seedMetadata((array) ($attributes['metadata'] ?? [])),
            'uuid' => $attributes['uuid'] ?? 'demo-wt-'.substr(hash('sha256', $attributes['wallet_id'].'|'.$attributes['idempotency_key']), 0, 24),
        ]));
        $walletTransaction->save();

        return $walletTransaction;
    }

    protected function upsertPlanMetadata(string $slug, array $metadata): Plan
    {
        $plan = $this->demoPlan($slug);
        $plan->metadata = array_merge((array) $plan->metadata, $this->seedMetadata($metadata));
        $plan->save();

        return $plan;
    }

    protected function upsertInvoice(array $attributes): Invoice
    {
        $invoice = Invoice::query()->firstOrNew(['uuid' => $attributes['uuid']]);
        $invoice->fill(array_merge($attributes, [
            'metadata' => $this->seedMetadata((array) ($attributes['metadata'] ?? [])),
        ]));
        $invoice->save();

        return $invoice;
    }

    protected function upsertInvoiceItem(int $invoiceId, string $type, string $description, int $quantity, int $unitAmount): InvoiceItem
    {
        $subtotalAmount = $quantity * $unitAmount;

        return InvoiceItem::query()->updateOrCreate(
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
                'metadata' => $this->seedMetadata(),
            ],
        );
    }

    protected function upsertPayment(array $attributes): Payment
    {
        $payment = Payment::query()->firstOrNew(['uuid' => $attributes['uuid']]);
        $payment->fill(array_merge($attributes, [
            'metadata' => $this->seedMetadata((array) ($attributes['metadata'] ?? [])),
            'ownership_metadata' => $this->seedMetadata((array) ($attributes['ownership_metadata'] ?? [])),
        ]));
        $payment->save();

        return $payment;
    }

    protected function upsertPaymentTransaction(array $attributes): PaymentTransaction
    {
        return PaymentTransaction::query()->updateOrCreate(
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
                'payload' => $this->seedMetadata((array) ($attributes['payload'] ?? [])),
            ],
        );
    }

    protected function upsertProviderAccount(array $attributes): PaymentProviderAccount
    {
        $credentials = (array) ($attributes['credentials'] ?? []);
        unset($attributes['credentials']);

        $account = PaymentProviderAccount::query()->firstOrNew([
            'uuid' => $attributes['uuid'],
        ]);
        $account->fill(array_merge($attributes, [
            'public_config' => array_merge([
                'seeded' => true,
            ], (array) ($attributes['public_config'] ?? [])),
            'capabilities' => (array) ($attributes['capabilities'] ?? []),
            'metadata' => $this->seedMetadata((array) ($attributes['metadata'] ?? [])),
        ]));
        if ($credentials !== []) {
            $account->setCredentials($credentials);
        }
        $account->save();

        return $account;
    }

    protected function upsertWebhookDelivery(
        int $paymentId,
        int $subscriptionId,
        ?int $invoiceId,
        string $event,
        string $status,
        int $attempts,
        int $statusCode,
        string $responseBody,
    ): WebhookDelivery {
        $uuid = 'demo-wh-'.substr(hash('sha256', $event.'|'.$status), 0, 24);

        $webhookDelivery = WebhookDelivery::query()->firstOrNew([
            'uuid' => $uuid,
        ]);
        $webhookDelivery->fill([
            'payment_id' => $paymentId,
            'subscription_id' => $subscriptionId,
            'invoice_id' => $invoiceId,
            'event' => $event,
            'status' => $status,
            'url' => 'https://example.test/webhooks/billing',
            'payload' => $this->seedMetadata(),
            'response_status' => $statusCode,
            'response_body' => $responseBody,
            'attempts' => $attempts,
            'max_attempts' => 5,
            'next_retry_at' => $status === 'failed' ? now()->addMinutes(15) : null,
            'last_attempt_at' => now(),
            'delivered_at' => $status === 'delivered' ? now() : null,
            'failed_at' => in_array($status, ['failed', 'permanently_failed'], true) ? now() : null,
            'metadata' => $this->seedMetadata(),
        ]);
        $webhookDelivery->save();

        return $webhookDelivery;
    }

    protected function upsertIdempotencyKey(int $userId, string $scope, string $method, string $endpoint, string $status, ?int $responseStatus, ?Payment $payment): IdempotencyKey
    {
        return IdempotencyKey::query()->updateOrCreate(
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

    protected function upsertRestriction(int $userId, string $type, string $scope, ?string $featureKey, string $reason, int $createdBy, bool $active): BillingRestriction
    {
        return BillingRestriction::query()->updateOrCreate(
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
                'metadata' => $this->seedMetadata(),
            ],
        );
    }

    protected function upsertFeatureOverride(
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
    ): FeatureOverride {
        return FeatureOverride::query()->updateOrCreate(
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
                'metadata' => $this->seedMetadata(),
            ],
        );
    }
}
