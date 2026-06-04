<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Billing\WalletAdjustmentRequest;
use App\Http\Resources\Billing\WalletTransactionResource;
use App\Models\User;
use App\Services\Billing\WalletTransactionService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class WalletAdjustmentController extends BaseController
{
    public function __construct(
        private readonly WalletTransactionService $walletTransactionService,
    ) {
    }

    public function store(WalletAdjustmentRequest $request): JsonResponse
    {
        $direction = (string) $request->input('direction');
        $actor = $request->user();

        if (! $actor->hasPermission('billing.wallets.adjust')
            && ! $actor->hasPermission("billing.wallets.{$direction}")) {
            abort(403);
        }

        $targetUser = User::query()->findOrFail($request->integer('user_id'));

        try {
            $transaction = $direction === 'credit'
                ? $this->walletTransactionService->manualCredit(
                    targetUser: $targetUser,
                    currencyCode: strtoupper((string) $request->input('currency')),
                    amount: $request->integer('amount'),
                    actor: $actor,
                    reason: (string) $request->input('reason'),
                    description: $request->input('description'),
                    reference: $request->input('reference'),
                    idempotencyKey: trim((string) $request->header('Idempotency-Key')),
                    metadata: (array) $request->input('metadata', []),
                )
                : $this->walletTransactionService->manualDebit(
                    targetUser: $targetUser,
                    currencyCode: strtoupper((string) $request->input('currency')),
                    amount: $request->integer('amount'),
                    actor: $actor,
                    reason: (string) $request->input('reason'),
                    description: $request->input('description'),
                    reference: $request->input('reference'),
                    idempotencyKey: trim((string) $request->header('Idempotency-Key')),
                    metadata: (array) $request->input('metadata', []),
                );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Wallet adjustment failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: (new WalletTransactionResource($transaction->load(['currency', 'payment'])))->resolve(),
            message: 'Wallet adjustment completed successfully.',
            statusCode: 201,
        );
    }
}
