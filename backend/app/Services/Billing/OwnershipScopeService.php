<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\User;
use RuntimeException;

class OwnershipScopeService
{
    /**
     * Resolve additive payment ownership without requiring company/seller scope.
     *
     * @return array{
     *     payer_user_id: int,
     *     company_id: ?int,
     *     seller_id: ?int,
     *     company: ?Company,
     *     seller: ?Seller,
     *     ownership_metadata: array<string, mixed>
     * }
     */
    public function resolveForPayment(User $payer, array $context = []): array
    {
        $seller = $this->resolveSeller($context['seller_id'] ?? null);
        $company = $this->resolveCompany($context['company_id'] ?? null);

        if ($seller?->company_id !== null) {
            if ($company !== null && (int) $company->id !== (int) $seller->company_id) {
                throw new RuntimeException('payment_ownership_scope_conflict');
            }

            $company ??= $seller->company;
        }

        if (($context['require_seller_customer'] ?? false) === true
            && $seller !== null
            && ! $seller->customerLinks()
                ->where('user_id', $payer->id)
                ->where('status', 'active')
                ->exists()) {
            throw new RuntimeException('payer_not_linked_to_seller');
        }

        return [
            'payer_user_id' => $payer->id,
            'company_id' => $company?->id,
            'seller_id' => $seller?->id,
            'company' => $company,
            'seller' => $seller,
            'ownership_metadata' => $this->resolveProviderAccountScope($company, $seller),
        ];
    }

    public function canActorAccessCompany(User $actor, Company $company): bool
    {
        if ($actor->isAdmin() || $actor->hasAnyPermission([
            'billing.companies.view',
            'billing.companies.manage',
            'billing.payments.view_company',
            'billing.payments.manage_company',
        ])) {
            return true;
        }

        return $company->members()
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->exists();
    }

    public function canActorAccessSeller(User $actor, Seller $seller): bool
    {
        if ($actor->isAdmin() || $actor->hasAnyPermission([
            'billing.sellers.view',
            'billing.sellers.manage',
            'billing.payments.view_seller',
            'billing.payments.manage_seller',
        ])) {
            return true;
        }

        if ((int) $seller->owner_user_id === (int) $actor->id) {
            return true;
        }

        return $seller->company !== null
            && $this->canActorAccessCompany($actor, $seller->company);
    }

    public function canActorAccessPayment(User $actor, Payment $payment): bool
    {
        if ((int) ($payment->payer_user_id ?? $payment->user_id) === (int) $actor->id) {
            return true;
        }

        if ($payment->seller !== null && $this->canActorAccessSeller($actor, $payment->seller)) {
            return true;
        }

        return $payment->company !== null
            && $this->canActorAccessCompany($actor, $payment->company);
    }

    /**
     * Provider resolution uses this same scope order later:
     * seller -> company -> user -> platform -> simulator default.
     *
     * @return array{scope: string, company_id: ?int, seller_id: ?int}
     */
    public function resolveProviderAccountScope(?Company $company, ?Seller $seller): array
    {
        return [
            'scope' => $seller !== null ? 'seller' : ($company !== null ? 'company' : 'user'),
            'company_id' => $company?->id,
            'seller_id' => $seller?->id,
        ];
    }

    private function resolveCompany(mixed $companyId): ?Company
    {
        if ($companyId === null) {
            return null;
        }

        $company = Company::query()->find($companyId);
        if (! $company) {
            throw new RuntimeException('company_not_found');
        }

        if ($company->status !== 'active') {
            throw new RuntimeException('company_not_active');
        }

        return $company;
    }

    private function resolveSeller(mixed $sellerId): ?Seller
    {
        if ($sellerId === null) {
            return null;
        }

        $seller = Seller::query()->with('company')->find($sellerId);
        if (! $seller) {
            throw new RuntimeException('seller_not_found');
        }

        if ($seller->status !== 'active') {
            throw new RuntimeException('seller_not_active');
        }

        if ($seller->company !== null && $seller->company->status !== 'active') {
            throw new RuntimeException('company_not_active');
        }

        return $seller;
    }
}
