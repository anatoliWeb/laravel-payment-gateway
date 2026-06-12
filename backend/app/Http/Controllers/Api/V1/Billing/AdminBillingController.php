<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Billing\Admin\AdminFeatureOverrideResource;
use App\Http\Resources\Billing\Admin\AdminIdempotencyKeyResource;
use App\Http\Resources\Billing\Admin\AdminPaymentResource;
use App\Http\Resources\Billing\Admin\AdminPaymentTransactionResource;
use App\Http\Resources\Billing\Admin\AdminProviderAccountResource;
use App\Http\Resources\Billing\Admin\AdminRestrictionResource;
use App\Http\Resources\Billing\Admin\AdminWalletResource;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Http\Resources\Billing\WalletTransactionResource;
use App\Models\BillingRestriction;
use App\Models\FeatureOverride;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\PaymentProviderAccount;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBillingController extends BaseController
{
    /**
     * Read-only admin billing API surface.
     *
     * WHY:
     * Admin/operator screens need safe list and detail lookups without
     * duplicating the underlying billing write services.
     */
    public function payments(Request $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.payments.view_any',
        ])) {
            return $response;
        }

        $query = Payment::query()
            ->withCount(['transactions', 'webhookDeliveries'])
            ->latest();

        $this->applyPaymentFilters($query, $request);

        return $this->paginatedResponse(
            $query->paginate($this->perPage($request))->withQueryString(),
            'Payments fetched successfully.',
            resourceClass: AdminPaymentResource::class,
        );
    }

    public function payment(Request $request, Payment $payment): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.payments.view_any',
        ])) {
            return $response;
        }

        return $this->successResponse(
            (new AdminPaymentResource($payment->loadCount(['transactions', 'webhookDeliveries'])))->resolve(),
            'Payment fetched successfully.',
        );
    }

    public function paymentTransactions(Request $request, Payment $payment): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.payments.view_transactions',
            'billing.payments.view_any',
        ])) {
            return $response;
        }

        $transactions = PaymentTransaction::query()
            ->where('payment_id', $payment->id)
            ->latest()
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedResponse(
            $transactions,
            'Payment transactions fetched successfully.',
            resourceClass: AdminPaymentTransactionResource::class,
        );
    }

    public function subscriptions(Request $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.subscriptions.view_any',
        ])) {
            return $response;
        }

        $query = Subscription::query()
            ->with('plan')
            ->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('uuid', 'like', "%{$search}%")
                    ->orWhereHas('plan', fn ($planQuery) => $planQuery->where('slug', 'like', "%{$search}%"))
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        return $this->paginatedResponse(
            $query->paginate($this->perPage($request))->withQueryString(),
            'Subscriptions fetched successfully.',
            resourceClass: SubscriptionResource::class,
        );
    }

    public function subscription(Request $request, Subscription $subscription): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.subscriptions.view_any',
        ])) {
            return $response;
        }

        return $this->successResponse(
            (new SubscriptionResource($subscription->load('plan')))->resolve(),
            'Subscription fetched successfully.',
        );
    }

    public function wallets(Request $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.wallets.view_any',
        ])) {
            return $response;
        }

        $query = Wallet::query()->with('balances.currency')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->whereHas('user', function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $this->paginatedResponse(
            $query->paginate($this->perPage($request))->withQueryString(),
            'Wallets fetched successfully.',
            resourceClass: AdminWalletResource::class,
        );
    }

    public function wallet(Request $request, Wallet $wallet): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.wallets.view_any',
        ])) {
            return $response;
        }

        return $this->successResponse(
            (new AdminWalletResource($wallet->load('balances.currency')))->resolve(),
            'Wallet fetched successfully.',
        );
    }

    public function walletTransactions(Request $request, Wallet $wallet): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.wallets.view_transactions',
            'billing.wallets.view_any',
        ])) {
            return $response;
        }

        $transactions = $wallet->transactions()
            ->with(['currency', 'payment'])
            ->latest()
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedResponse(
            $transactions,
            'Wallet transactions fetched successfully.',
            resourceClass: WalletTransactionResource::class,
        );
    }

    public function idempotencyKeys(Request $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.idempotency.view_any',
        ])) {
            return $response;
        }

        $query = IdempotencyKey::query()->with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('scope')) {
            $query->where('scope', (string) $request->string('scope'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('scope', 'like', "%{$search}%")
                    ->orWhere('method', 'like', "%{$search}%")
                    ->orWhere('endpoint', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        return $this->paginatedResponse(
            $query->paginate($this->perPage($request))->withQueryString(),
            'Idempotency keys fetched successfully.',
            resourceClass: AdminIdempotencyKeyResource::class,
        );
    }

    public function idempotencyKey(Request $request, IdempotencyKey $idempotencyKey): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.idempotency.view_any',
        ])) {
            return $response;
        }

        return $this->successResponse(
            (new AdminIdempotencyKeyResource($idempotencyKey->load('user')))->resolve(),
            'Idempotency key fetched successfully.',
        );
    }

    public function providerAccounts(Request $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.provider_accounts.view_any',
        ])) {
            return $response;
        }

        $query = PaymentProviderAccount::query()->latest();

        if ($request->filled('provider')) {
            $query->where('provider', (string) $request->string('provider'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('uuid', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('provider', 'like', "%{$search}%");
            });
        }

        return $this->paginatedResponse(
            $query->paginate($this->perPage($request))->withQueryString(),
            'Provider accounts fetched successfully.',
            resourceClass: AdminProviderAccountResource::class,
        );
    }

    public function providerAccount(Request $request, PaymentProviderAccount $providerAccount): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.provider_accounts.view_any',
        ])) {
            return $response;
        }

        return $this->successResponse(
            (new AdminProviderAccountResource($providerAccount))->resolve(),
            'Provider account fetched successfully.',
        );
    }

    public function restrictions(Request $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.restrictions.view_any',
        ])) {
            return $response;
        }

        $query = BillingRestriction::query()->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->string('type'));
        }

        if ($request->filled('scope')) {
            $query->where('scope', (string) $request->string('scope'));
        }

        return $this->paginatedResponse(
            $query->paginate($this->perPage($request))->withQueryString(),
            'Billing restrictions fetched successfully.',
            resourceClass: AdminRestrictionResource::class,
        );
    }

    public function restriction(Request $request, BillingRestriction $billingRestriction): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.restrictions.view_any',
        ])) {
            return $response;
        }

        return $this->successResponse(
            (new AdminRestrictionResource($billingRestriction))->resolve(),
            'Billing restriction fetched successfully.',
        );
    }

    public function featureOverrides(Request $request): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.overrides.view_any',
        ])) {
            return $response;
        }

        $query = FeatureOverride::query()->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->integer('subscription_id'));
        }

        if ($request->filled('feature_key')) {
            $query->where('feature_key', (string) $request->string('feature_key'));
        }

        return $this->paginatedResponse(
            $query->paginate($this->perPage($request))->withQueryString(),
            'Feature overrides fetched successfully.',
            resourceClass: AdminFeatureOverrideResource::class,
        );
    }

    public function featureOverride(Request $request, FeatureOverride $featureOverride): JsonResponse
    {
        if ($response = $this->ensureAccess($request->user(), [
            'billing.overrides.view_any',
        ])) {
            return $response;
        }

        return $this->successResponse(
            (new AdminFeatureOverrideResource($featureOverride))->resolve(),
            'Feature override fetched successfully.',
        );
    }

    private function ensureAccess(?User $actor, array $permissions): ?JsonResponse
    {
        if ($actor instanceof User && ($actor->isAdmin() || $actor->hasAnyPermission($permissions))) {
            return null;
        }

        return $this->errorResponse('Forbidden.', ['code' => 'forbidden'], 403, 'forbidden');
    }

    private function perPage(Request $request): int
    {
        return max(1, min((int) $request->query('per_page', 15), 100));
    }

    private function applyPaymentFilters($query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('seller_id')) {
            $query->where('seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('provider')) {
            $query->where('provider', (string) $request->string('provider'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', (string) $request->string('payment_method'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('uuid', 'like', "%{$search}%")
                    ->orWhere('provider_reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }
    }
}
