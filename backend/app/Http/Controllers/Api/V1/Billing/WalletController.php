<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Billing\WalletTopUpRequest;
use App\Http\Resources\Billing\WalletBalanceResource;
use App\Http\Resources\Billing\WalletResource;
use App\Http\Resources\Billing\WalletTransactionResource;
use App\Http\Resources\Payments\PaymentResource;
use App\Services\Billing\WalletService;
use App\Services\Billing\WalletTopUpService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class WalletController extends BaseController
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly WalletTopUpService $walletTopUpService,
    ) {
    }

    public function show(): JsonResponse
    {
        $wallet = $this->walletService
            ->getOrCreateWallet(request()->user())
            ->load('balances.currency');

        return $this->successResponse(
            data: (new WalletResource($wallet))->resolve(),
            message: 'Wallet fetched successfully.',
        );
    }

    public function balances(): JsonResponse
    {
        $wallet = $this->walletService
            ->getOrCreateWallet(request()->user())
            ->load('balances.currency');

        return $this->successResponse(
            data: WalletBalanceResource::collection($wallet->balances)->resolve(),
            message: 'Wallet balances fetched successfully.',
        );
    }

    public function transactions(): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet(request()->user());
        $perPage = min(max((int) request()->query('per_page', 15), 5), 100);

        $transactions = $wallet->transactions()
            ->with(['currency', 'payment'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            paginator: $transactions,
            message: 'Wallet transactions fetched successfully.',
            resourceClass: WalletTransactionResource::class,
        );
    }

    public function topUp(WalletTopUpRequest $request): JsonResponse
    {
        try {
            $result = $this->walletTopUpService->topUp(
                user: $request->user(),
                amount: $request->integer('amount'),
                currencyCode: strtoupper((string) $request->input('currency')),
                paymentMethodId: $request->integer('payment_method_id') ?: null,
                idempotencyKey: (string) $request->header('Idempotency-Key'),
                metadata: (array) $request->input('metadata', []),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Wallet top-up failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: [
                'payment' => (new PaymentResource($result['payment']))->resolve(),
                'wallet_transaction' => (new WalletTransactionResource($result['wallet_transaction']->load(['currency', 'payment'])))->resolve(),
            ],
            message: 'Wallet top-up created successfully.',
            statusCode: 201,
        );
    }
}
