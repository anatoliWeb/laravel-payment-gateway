<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\DTO\Payments\CreatePaymentData;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Billing\CreatePaymentRequest;
use App\Http\Resources\Payments\PaymentResource;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaymentController extends BaseController
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
    }

    public function store(CreatePaymentRequest $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->createPayment(
                CreatePaymentData::fromRequest($request),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Payment creation failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: (new PaymentResource($payment))->resolve(),
            message: 'Payment created successfully.',
            statusCode: 201,
        );
    }
}
