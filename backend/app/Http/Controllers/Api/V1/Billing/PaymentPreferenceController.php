<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Billing\UpdatePaymentPreferenceRequest;
use App\Http\Resources\Billing\PaymentPreferenceResource;
use App\Services\Billing\PaymentPreferenceService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaymentPreferenceController extends BaseController
{
    public function __construct(
        private readonly PaymentPreferenceService $paymentPreferenceService,
    ) {
    }

    public function show(): JsonResponse
    {
        $preference = $this->paymentPreferenceService
            ->getOrCreatePreferences(request()->user())
            ->load(['defaultPaymentMethod', 'autoTopUpCurrency']);

        return $this->successResponse(
            data: (new PaymentPreferenceResource($preference))->resolve(),
            message: 'Payment preferences fetched successfully.',
        );
    }

    public function update(UpdatePaymentPreferenceRequest $request): JsonResponse
    {
        try {
            $preference = $this->paymentPreferenceService
                ->updatePreferences($request->user(), $request->validated())
                ->load(['defaultPaymentMethod', 'autoTopUpCurrency']);
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Payment preference update failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: (new PaymentPreferenceResource($preference))->resolve(),
            message: 'Payment preferences updated successfully.',
        );
    }
}
