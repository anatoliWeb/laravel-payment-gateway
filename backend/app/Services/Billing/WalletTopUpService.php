<?php

namespace App\Services\Billing;

use App\DTO\Payments\CreatePaymentData;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\ActivityService;
use App\Services\Payments\IdempotencyService;
use App\Services\Payments\PaymentService;
use RuntimeException;
use Throwable;

class WalletTopUpService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly WalletTransactionService $walletTransactionService,
        private readonly ActivityService $activityService,
        private readonly IdempotencyService $idempotencyService,
    ) {}

    /**
     * Manual wallet top-up through simulator-safe payment-method flow.
     *
     * This is intentionally separate from AutoTopUpService because manual
     * top-up must not depend on user threshold, automation consent, or limits.
     *
     * @return array{payment: Payment, wallet_transaction: WalletTransaction}
     */
    public function topUp(
        User $user,
        int $amount,
        string $currencyCode,
        ?int $paymentMethodId,
        string $idempotencyKey,
        array $metadata = [],
    ): array {
        if ($amount <= 0) {
            throw new RuntimeException('wallet_amount_must_be_positive');
        }

        $payload = [
            'amount' => $amount,
            'currency' => strtoupper($currencyCode),
            'payment_method_id' => $paymentMethodId,
            'metadata' => $metadata,
        ];
        $replay = $this->idempotencyService->replay($idempotencyKey, 'wallet.top_up', $payload, $user);
        if ($replay !== null) {
            return $this->replayTopUp($replay);
        }

        $idempotencyRecord = $this->idempotencyService->start($idempotencyKey, 'wallet.top_up', $payload, $user);
        if (in_array($idempotencyRecord->status, ['completed', 'failed'], true)) {
            return $this->replayTopUp((array) $idempotencyRecord->response_body);
        }

        try {
            $payment = $this->paymentService->createPayment(new CreatePaymentData(
                user: $user,
                subscriptionId: null,
                planSlug: null,
                amount: $amount,
                currency: strtoupper($currencyCode),
                paymentSource: 'payment_method',
                paymentStrategy: 'payment_method_only',
                paymentMethodId: $paymentMethodId,
                callbackUrl: null,
                description: 'Manual wallet top-up',
                metadata: array_merge($metadata, [
                    'source' => 'manual_wallet_top_up',
                    'manual_wallet_top_up' => true,
                ]),
                idempotencyKey: 'wallet_top_up:'.hash('sha256', $user->id.'|'.$idempotencyKey).':payment',
            ));

            $walletTransaction = $this->walletTransactionService->credit(
                user: $user,
                currencyCode: strtoupper($currencyCode),
                amount: $amount,
                type: 'top_up',
                idempotencyKey: 'wallet_top_up:'.hash('sha256', $user->id.'|'.$idempotencyKey),
                metadata: [
                    'source' => 'manual_wallet_top_up',
                    'payment_id' => $payment->id,
                    'reason' => 'manual_wallet_top_up',
                ],
            );

            $this->idempotencyService->complete(
                $idempotencyRecord,
                [
                    'payment_id' => $payment->id,
                    'wallet_transaction_id' => $walletTransaction->id,
                ],
                $walletTransaction->id,
                WalletTransaction::class,
            );
        } catch (Throwable $exception) {
            $this->idempotencyService->fail(
                $idempotencyRecord,
                $exception->getMessage() ?: 'wallet_top_up_failed',
            );

            throw $exception;
        }

        $this->recordActivity($user, 'billing.wallet_top_up_succeeded', [
            'payment_id' => $payment->id,
            'payment_uuid' => $payment->uuid,
            'wallet_transaction_id' => $walletTransaction->id,
            'amount' => $amount,
            'currency' => strtoupper($currencyCode),
        ]);

        return [
            'payment' => $payment->refresh(),
            'wallet_transaction' => $walletTransaction->refresh(),
        ];
    }

    private function replayTopUp(array $payload): array
    {
        if (isset($payload['error_code'])) {
            throw new RuntimeException((string) $payload['error_code']);
        }

        $payment = Payment::query()->find($payload['payment_id'] ?? null);
        $walletTransaction = WalletTransaction::query()->find($payload['wallet_transaction_id'] ?? null);

        if (! $payment || ! $walletTransaction) {
            throw new RuntimeException('idempotency_replay_resource_missing');
        }

        return [
            'payment' => $payment,
            'wallet_transaction' => $walletTransaction,
        ];
    }

    private function recordActivity(User $user, string $action, array $metadata): void
    {
        try {
            $this->activityService->log($user->id, $action, 'Billing wallet top-up event', array_merge([
                'source' => 'wallet_top_up_service',
                'module' => 'billing',
            ], $metadata));
        } catch (Throwable) {
            // Wallet mutation has already happened; activity failure must not roll it back.
        }
    }
}
