<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WalletTransactionService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function credit(
        User $user,
        string $currencyCode,
        int $amount,
        string $type = 'top_up',
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): WalletTransaction {
        $this->assertPositiveAmount($amount);

        return $this->applyBalanceMutation(
            user: $user,
            currencyCode: $currencyCode,
            amount: $amount,
            type: $type,
            direction: 'credit',
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            mutator: function (WalletBalance $balance) use ($amount): array {
                $beforeAvailable = $balance->available_amount;
                $beforeHeld = $balance->held_amount;
                $balance->available_amount = $beforeAvailable + $amount;

                return [$beforeAvailable, $balance->available_amount, $beforeHeld, $beforeHeld];
            },
        );
    }

    public function debit(
        User $user,
        string $currencyCode,
        int $amount,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): WalletTransaction {
        $this->assertPositiveAmount($amount);

        return $this->applyBalanceMutation(
            user: $user,
            currencyCode: $currencyCode,
            amount: $amount,
            type: 'debit',
            direction: 'debit',
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            mutator: function (WalletBalance $balance) use ($amount): array {
                if ($balance->available_amount < $amount) {
                    throw new RuntimeException('insufficient_wallet_balance');
                }

                $beforeAvailable = $balance->available_amount;
                $beforeHeld = $balance->held_amount;
                $balance->available_amount = $beforeAvailable - $amount;

                return [$beforeAvailable, $balance->available_amount, $beforeHeld, $beforeHeld];
            },
        );
    }

    public function hold(
        User $user,
        string $currencyCode,
        int $amount,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): WalletTransaction {
        $this->assertPositiveAmount($amount);

        return $this->applyBalanceMutation(
            user: $user,
            currencyCode: $currencyCode,
            amount: $amount,
            type: 'hold',
            direction: 'neutral',
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            mutator: function (WalletBalance $balance) use ($amount): array {
                if ($balance->available_amount < $amount) {
                    throw new RuntimeException('insufficient_wallet_balance');
                }

                $beforeAvailable = $balance->available_amount;
                $beforeHeld = $balance->held_amount;
                $balance->available_amount = $beforeAvailable - $amount;
                $balance->held_amount = $beforeHeld + $amount;

                return [$beforeAvailable, $balance->available_amount, $beforeHeld, $balance->held_amount];
            },
        );
    }

    public function release(User $user, string $currencyCode, int $amount, array $metadata = []): WalletTransaction
    {
        $this->assertPositiveAmount($amount);

        return $this->applyBalanceMutation(
            user: $user,
            currencyCode: $currencyCode,
            amount: $amount,
            type: 'release',
            direction: 'neutral',
            idempotencyKey: null,
            metadata: $metadata,
            mutator: function (WalletBalance $balance) use ($amount): array {
                if ($balance->held_amount < $amount) {
                    throw new RuntimeException('insufficient_held_wallet_balance');
                }

                $beforeAvailable = $balance->available_amount;
                $beforeHeld = $balance->held_amount;
                $balance->available_amount = $beforeAvailable + $amount;
                $balance->held_amount = $beforeHeld - $amount;

                return [$beforeAvailable, $balance->available_amount, $beforeHeld, $balance->held_amount];
            },
        );
    }

    public function refund(User $user, string $currencyCode, int $amount, array $metadata = []): WalletTransaction
    {
        return $this->credit($user, $currencyCode, $amount, 'refund', null, $metadata);
    }

    private function applyBalanceMutation(
        User $user,
        string $currencyCode,
        int $amount,
        string $type,
        string $direction,
        ?string $idempotencyKey,
        array $metadata,
        callable $mutator,
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $currencyCode, $amount, $type, $direction, $idempotencyKey, $metadata, $mutator): WalletTransaction {
            $balance = $this->walletService->getOrCreateBalance($user, $currencyCode);
            if (! $balance) {
                throw new RuntimeException('wallet_currency_not_available');
            }

            if ($idempotencyKey !== null) {
                $existing = WalletTransaction::query()
                    ->where('wallet_id', $balance->wallet_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('status', 'completed')
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            /** @var WalletBalance $lockedBalance */
            $lockedBalance = WalletBalance::query()
                ->whereKey($balance->id)
                ->lockForUpdate()
                ->firstOrFail();

            [$availableBefore, $availableAfter, $heldBefore, $heldAfter] = $mutator($lockedBalance);
            $lockedBalance->save();

            // WHY: Transactions are ledger history; balance rows hold current state.
            return WalletTransaction::query()->create([
                'uuid' => (string) Str::uuid(),
                'wallet_id' => $lockedBalance->wallet_id,
                'wallet_balance_id' => $lockedBalance->id,
                'currency_id' => $lockedBalance->currency_id,
                'payment_id' => $metadata['payment_id'] ?? null,
                'subscription_id' => $metadata['subscription_id'] ?? null,
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'balance_available_before' => $availableBefore,
                'balance_available_after' => $availableAfter,
                'balance_held_before' => $heldBefore,
                'balance_held_after' => $heldAfter,
                'idempotency_key' => $idempotencyKey,
                'reference_type' => $metadata['reference_type'] ?? null,
                'reference_id' => $metadata['reference_id'] ?? null,
                'reason' => $metadata['reason'] ?? null,
                'status' => 'completed',
                'metadata' => array_merge(['source' => 'wallet_transaction_service'], $metadata),
            ]);
        });
    }

    private function assertPositiveAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new RuntimeException('wallet_amount_must_be_positive');
        }
    }
}
