<?php

namespace App\Services\Billing;

use App\Models\Currency;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    public function getOrCreateWallet(User $user): Wallet
    {
        return DB::transaction(function () use ($user): Wallet {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($wallet) {
                return $wallet;
            }

            return Wallet::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'status' => 'active',
                'metadata' => ['source' => 'wallet_service'],
            ]);
        });
    }

    public function getBalance(User $user, string $currencyCode): ?WalletBalance
    {
        $currency = $this->findActiveCurrency($currencyCode);
        if (! $currency) {
            return null;
        }

        $wallet = $user->wallet;
        if (! $wallet) {
            return null;
        }

        return $wallet->balances()
            ->where('currency_id', $currency->id)
            ->first();
    }

    public function getOrCreateBalance(User $user, string $currencyCode): ?WalletBalance
    {
        $currency = $this->findActiveCurrency($currencyCode);
        if (! $currency) {
            return null;
        }

        return DB::transaction(function () use ($user, $currency): WalletBalance {
            $wallet = $this->getOrCreateWallet($user);

            $balance = WalletBalance::query()
                ->where('wallet_id', $wallet->id)
                ->where('currency_id', $currency->id)
                ->lockForUpdate()
                ->first();

            if ($balance) {
                return $balance;
            }

            return WalletBalance::query()->create([
                'wallet_id' => $wallet->id,
                'currency_id' => $currency->id,
                'available_amount' => 0,
                'held_amount' => 0,
                'metadata' => ['source' => 'wallet_service'],
            ]);
        });
    }

    private function findActiveCurrency(string $currencyCode): ?Currency
    {
        return Currency::query()
            ->where('code', strtoupper($currencyCode))
            ->where('is_active', true)
            ->first();
    }
}
