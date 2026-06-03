<?php

namespace App\Services\Billing;

use App\DTO\Payments\CreatePaymentData;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\ActivityService;
use App\Services\Payments\PaymentService;
use RuntimeException;
use Throwable;

class WalletTopUpService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly WalletTransactionService $walletTransactionService,
        private readonly ActivityService $activityService,
    ) {
    }

    /**
     * Manual wallet top-up through simulator-safe payment-method flow.
     *
     * This is intentionally separate from AutoTopUpService because manual
     * top-up must not depend on user threshold, automation consent, or limits.
     *
     * @return array{payment: \App\Models\Payment, wallet_transaction: WalletTransaction}
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
            idempotencyKey: $idempotencyKey,
        ));

        $walletTransaction = $this->walletTransactionService->credit(
            user: $user,
            currencyCode: strtoupper($currencyCode),
            amount: $amount,
            type: 'top_up',
            idempotencyKey: 'wallet_top_up:'.$idempotencyKey,
            metadata: [
                'source' => 'manual_wallet_top_up',
                'payment_id' => $payment->id,
                'reason' => 'manual_wallet_top_up',
            ],
        );

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
