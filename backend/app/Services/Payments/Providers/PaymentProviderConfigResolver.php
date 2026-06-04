<?php

namespace App\Services\Payments\Providers;

use App\Models\Company;
use App\Models\PaymentProviderAccount;
use App\Models\Seller;
use App\Models\User;
use App\Services\Payments\Providers\DTO\ProviderConfigData;
use RuntimeException;

class PaymentProviderConfigResolver
{
    public function resolve(
        string $provider,
        ?User $user = null,
        ?PaymentProviderAccount $explicitAccount = null,
        ?Company $company = null,
        ?Seller $seller = null,
    ): ProviderConfigData {
        $provider = strtolower($provider);

        if ($explicitAccount !== null) {
            return $this->fromAccount($provider, $user, $explicitAccount, $company, $seller);
        }

        if ($seller !== null) {
            $account = PaymentProviderAccount::query()
                ->where('seller_id', $seller->id)
                ->where(function ($query) use ($seller): void {
                    $query->whereNull('company_id');

                    if ($seller->company_id !== null) {
                        $query->orWhere('company_id', $seller->company_id);
                    }
                })
                ->where('provider', $provider)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if ($account) {
                return $this->fromAccount($provider, $user, $account, $company, $seller);
            }
        }

        if ($company !== null) {
            $account = PaymentProviderAccount::query()
                ->where('company_id', $company->id)
                ->whereNull('seller_id')
                ->where('provider', $provider)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if ($account) {
                return $this->fromAccount($provider, $user, $account, $company, $seller);
            }
        }

        if ($user !== null) {
            $account = PaymentProviderAccount::query()
                ->where('user_id', $user->id)
                ->whereNull('company_id')
                ->whereNull('seller_id')
                ->where('provider', $provider)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if ($account) {
                return $this->fromAccount($provider, $user, $account, $company, $seller);
            }
        }

        $platform = (array) config("billing.providers.platform.{$provider}", []);
        if (($platform['enabled'] ?? false) === true) {
            return new ProviderConfigData(
                provider: $provider,
                source: 'env',
                enabled: true,
                mode: (string) ($platform['mode'] ?? 'test'),
                credentials: (array) ($platform['credentials'] ?? []),
                publicConfig: (array) ($platform['public_config'] ?? []),
            );
        }

        if (in_array($provider, ['simulator', 'manual', 'internal_wallet'], true)
            && config('billing.providers.simulator_default', true)) {
            return new ProviderConfigData(
                provider: $provider,
                source: 'default',
                enabled: true,
                mode: 'test',
                publicConfig: ['simulator_safe' => true],
            );
        }

        return new ProviderConfigData(
            provider: $provider,
            source: 'disabled',
            enabled: false,
            errorCode: config('billing.providers.external_enabled', false)
                ? 'provider_not_configured'
                : 'provider_disabled',
        );
    }

    private function fromAccount(
        string $provider,
        ?User $user,
        PaymentProviderAccount $account,
        ?Company $company,
        ?Seller $seller,
    ): ProviderConfigData {
        $matchesSeller = $seller !== null
            && (int) $account->seller_id === (int) $seller->id
            && ($account->company_id === null
                || (int) $account->company_id === (int) $seller->company_id);
        $matchesCompany = $company !== null
            && $account->seller_id === null
            && (int) $account->company_id === (int) $company->id;
        $matchesUser = $user !== null
            && $account->seller_id === null
            && $account->company_id === null
            && (int) $account->user_id === (int) $user->id;

        if (! $matchesSeller && ! $matchesCompany && ! $matchesUser) {
            throw new RuntimeException('provider_account_not_accessible');
        }

        if ($account->provider !== $provider || $account->status !== 'active') {
            throw new RuntimeException('provider_not_configured');
        }

        return new ProviderConfigData(
            provider: $provider,
            source: 'database',
            enabled: true,
            mode: $account->mode,
            credentials: $account->getCredentials(),
            publicConfig: $account->public_config ?? [],
            providerAccountId: $account->id,
        );
    }
}
