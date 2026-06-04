<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use App\Services\ActivityService;
use App\Services\Payments\IdempotencyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WalletTransactionService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly ActivityService $activityService,
        private readonly IdempotencyService $idempotencyService,
    ) {}

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

    public function manualCredit(
        User $targetUser,
        string $currencyCode,
        int $amount,
        User $actor,
        string $reason,
        ?string $description = null,
        ?string $reference = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): WalletTransaction {
        return $this->manualAdjustment(
            targetUser: $targetUser,
            currencyCode: $currencyCode,
            amount: $amount,
            actor: $actor,
            direction: 'credit',
            reason: $reason,
            description: $description,
            reference: $reference,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );
    }

    public function manualDebit(
        User $targetUser,
        string $currencyCode,
        int $amount,
        User $actor,
        string $reason,
        ?string $description = null,
        ?string $reference = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): WalletTransaction {
        return $this->manualAdjustment(
            targetUser: $targetUser,
            currencyCode: $currencyCode,
            amount: $amount,
            actor: $actor,
            direction: 'debit',
            reason: $reason,
            description: $description,
            reference: $reference,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );
    }

    private function manualAdjustment(
        User $targetUser,
        string $currencyCode,
        int $amount,
        User $actor,
        string $direction,
        string $reason,
        ?string $description,
        ?string $reference,
        ?string $idempotencyKey,
        array $metadata,
    ): WalletTransaction {
        $this->assertPositiveAmount($amount);

        if (trim($reason) === '') {
            throw new RuntimeException('wallet_adjustment_reason_required');
        }

        if (trim((string) $idempotencyKey) === '') {
            throw new RuntimeException('idempotency_key_required');
        }

        $ledgerIdempotencyKey = 'wallet_adjustment:'.hash('sha256', $actor->id.'|'.$idempotencyKey);
        $idempotencyPayload = [
            'target_user_id' => $targetUser->id,
            'currency' => strtoupper($currencyCode),
            'amount' => $amount,
            'direction' => $direction,
            'reason' => $reason,
            'description' => $description,
            'reference' => $reference,
            'metadata' => $metadata,
        ];
        $replay = $this->idempotencyService->replay(
            $idempotencyKey,
            'wallet.adjustment',
            $idempotencyPayload,
            $actor,
        );
        if ($replay !== null) {
            return $this->replayWalletTransaction($replay);
        }

        $idempotencyRecord = $this->idempotencyService->start(
            $idempotencyKey,
            'wallet.adjustment',
            $idempotencyPayload,
            $actor,
        );
        if (in_array($idempotencyRecord->status, ['completed', 'failed'], true)) {
            return $this->replayWalletTransaction((array) $idempotencyRecord->response_body);
        }

        try {
            [$transaction, $created] = DB::transaction(function () use (
                $targetUser,
                $currencyCode,
                $amount,
                $actor,
                $direction,
                $reason,
                $description,
                $reference,
                $ledgerIdempotencyKey,
                $metadata,
            ): array {
                $balance = $this->walletService->getOrCreateBalance($targetUser, $currencyCode);
                if (! $balance) {
                    throw new RuntimeException('wallet_currency_not_available');
                }

                // Manual adjustments are low-volume operator actions. Locking the
                // wallet serializes idempotency checks across all its currencies.
                Wallet::query()
                    ->whereKey($balance->wallet_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = WalletTransaction::query()
                    ->where('wallet_id', $balance->wallet_id)
                    ->where('idempotency_key', $ledgerIdempotencyKey)
                    ->where('status', 'completed')
                    ->first();

                if ($existing) {
                    if ($existing->currency_id !== $balance->currency_id
                        || $existing->type !== 'adjustment'
                        || $existing->direction !== $direction
                        || $existing->amount !== $amount) {
                        throw new RuntimeException('idempotency_key_conflict');
                    }

                    return [$existing, false];
                }

                $lockedBalance = WalletBalance::query()
                    ->whereKey($balance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($direction === 'debit' && $lockedBalance->available_amount < $amount) {
                    throw new RuntimeException('insufficient_wallet_balance');
                }

                $availableBefore = $lockedBalance->available_amount;
                $heldBefore = $lockedBalance->held_amount;
                $lockedBalance->available_amount = $direction === 'credit'
                    ? $availableBefore + $amount
                    : $availableBefore - $amount;
                $lockedBalance->save();

                $safeMetadata = array_merge(
                    ['source' => 'manual_wallet_adjustment'],
                    $this->sanitizeMetadata($metadata),
                    [
                        'actor_id' => $actor->id,
                        'target_user_id' => $targetUser->id,
                        'reason' => $reason,
                        'description' => $description,
                        'reference' => $reference,
                        'adjustment_type' => "manual_{$direction}",
                    ],
                );

                $transaction = WalletTransaction::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'wallet_id' => $lockedBalance->wallet_id,
                    'wallet_balance_id' => $lockedBalance->id,
                    'currency_id' => $lockedBalance->currency_id,
                    'type' => 'adjustment',
                    'direction' => $direction,
                    'amount' => $amount,
                    'balance_available_before' => $availableBefore,
                    'balance_available_after' => $lockedBalance->available_amount,
                    'balance_held_before' => $heldBefore,
                    'balance_held_after' => $heldBefore,
                    'idempotency_key' => $ledgerIdempotencyKey,
                    'reason' => $reason,
                    'status' => 'completed',
                    'metadata' => $safeMetadata,
                ]);

                return [$transaction, true];
            });

            $this->idempotencyService->complete(
                $idempotencyRecord,
                ['wallet_transaction_id' => $transaction->id],
                $transaction->id,
                WalletTransaction::class,
            );
        } catch (Throwable $exception) {
            $this->idempotencyService->fail(
                $idempotencyRecord,
                $exception->getMessage() ?: 'wallet_adjustment_failed',
            );

            throw $exception;
        }

        if ($created) {
            $this->recordManualAdjustmentActivity($transaction, $targetUser, $actor, $direction, $reference);
        }

        return $transaction;
    }

    private function replayWalletTransaction(array $payload): WalletTransaction
    {
        if (isset($payload['error_code'])) {
            throw new RuntimeException((string) $payload['error_code']);
        }

        $transaction = WalletTransaction::query()->find($payload['wallet_transaction_id'] ?? null);
        if (! $transaction) {
            throw new RuntimeException('idempotency_replay_resource_missing');
        }

        return $transaction;
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

    private function sanitizeMetadata(array $metadata): array
    {
        $forbidden = ['token', 'secret', 'password', 'private_key', 'card_number', 'cvv', 'cvc', 'pan', 'security_code'];

        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), $forbidden, true)) {
                unset($metadata[$key]);

                continue;
            }

            if (is_array($value)) {
                $metadata[$key] = $this->sanitizeMetadata($value);
            }
        }

        return $metadata;
    }

    private function recordManualAdjustmentActivity(
        WalletTransaction $transaction,
        User $targetUser,
        User $actor,
        string $direction,
        ?string $reference,
    ): void {
        try {
            $this->activityService->log($actor->id, "billing.wallet_manual_{$direction}", 'Wallet adjustment completed', [
                'source' => 'wallet_transaction_service',
                'module' => 'billing',
                'target_user_id' => $targetUser->id,
                'actor_id' => $actor->id,
                'currency' => $transaction->currency?->code,
                'amount' => $transaction->amount,
                'wallet_transaction_id' => $transaction->id,
                'reason' => $transaction->reason,
                'reference' => $reference,
                'balance_available_before' => $transaction->balance_available_before,
                'balance_available_after' => $transaction->balance_available_after,
            ]);
        } catch (Throwable) {
            // Ledger integrity must not depend on activity logging availability.
        }
    }
}
