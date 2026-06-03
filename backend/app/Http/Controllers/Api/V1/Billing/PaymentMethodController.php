<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Billing\StorePaymentMethodRequest;
use App\Http\Requests\Api\V1\Billing\UpdatePaymentMethodRequest;
use App\Http\Resources\Billing\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Services\Billing\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaymentMethodController extends BaseController
{
    public function __construct(
        private readonly PaymentMethodService $paymentMethodService,
    ) {
    }

    public function index(): JsonResponse
    {
        $methods = $this->paymentMethodService->getUserPaymentMethods(request()->user());

        return $this->successResponse(
            data: PaymentMethodResource::collection($methods)->resolve(),
            message: 'Payment methods fetched successfully.',
        );
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        try {
            $paymentMethod = $this->paymentMethodService->createPaymentMethod($request->user(), $request->validated());
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Payment method creation failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: (new PaymentMethodResource($paymentMethod))->resolve(),
            message: 'Payment method created successfully.',
            statusCode: 201,
        );
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            $paymentMethod = $this->paymentMethodService->updatePaymentMethod(
                $request->user(),
                $paymentMethod,
                $request->validated(),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Payment method update failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: (new PaymentMethodResource($paymentMethod))->resolve(),
            message: 'Payment method updated successfully.',
        );
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            $paymentMethod = $this->paymentMethodService->deactivatePaymentMethod(request()->user(), $paymentMethod);
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Payment method deletion failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: (new PaymentMethodResource($paymentMethod))->resolve(),
            message: 'Payment method deactivated successfully.',
        );
    }

    public function setDefault(PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            if ($paymentMethod->status !== 'active') {
                throw new RuntimeException('payment_method_not_allowed');
            }

            $paymentMethod = $this->paymentMethodService->setDefaultPaymentMethod(request()->user(), $paymentMethod);
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Default payment method update failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: (new PaymentMethodResource($paymentMethod))->resolve(),
            message: 'Default payment method updated successfully.',
        );
    }
}
