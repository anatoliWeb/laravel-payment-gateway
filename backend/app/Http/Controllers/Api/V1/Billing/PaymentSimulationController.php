<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Billing\SimulatePaymentFailureRequest;
use App\Http\Requests\Api\V1\Billing\SimulatePaymentSuccessRequest;
use App\Http\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentSimulationService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaymentSimulationController extends BaseController
{
    public function __construct(
        private readonly PaymentSimulationService $paymentSimulationService,
    ) {}

    public function success(SimulatePaymentSuccessRequest $request, Payment $payment): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $payment = $this->paymentSimulationService->simulateSuccess(
                $payment,
                $actor,
                (array) $request->input('metadata', []),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Payment simulation failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: (new PaymentResource($payment))->resolve(),
            message: 'Payment success simulated successfully.',
        );
    }

    public function failure(SimulatePaymentFailureRequest $request, Payment $payment): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $payment = $this->paymentSimulationService->simulateFailure(
                $payment,
                $actor,
                (string) $request->input('reason'),
                (array) $request->input('metadata', []),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Payment simulation failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: (new PaymentResource($payment))->resolve(),
            message: 'Payment failure simulated successfully.',
        );
    }
}
