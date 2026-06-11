<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Payments\WebhookDeliveryResource;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\Billing\OwnershipScopeService;
use App\Services\Payments\WebhookDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WebhookDeliveryController extends BaseController
{
    public function __construct(
        private readonly WebhookDeliveryService $webhookDeliveryService,
        private readonly OwnershipScopeService $ownershipScopeService,
    ) {}

    public function indexForPayment(Request $request, Payment $payment): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if (! $this->ownershipScopeService->canActorAccessPayment($actor, $payment)) {
            return $this->errorResponse('Payment not found.', null, 404, 'payment_not_found');
        }

        $perPage = max(1, min((int) $request->query('per_page', 25), 100));
        $paginator = WebhookDelivery::query()
            ->where('payment_id', $payment->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Webhook deliveries fetched successfully.',
            resourceClass: WebhookDeliveryResource::class,
        );
    }

    public function retry(Request $request, WebhookDelivery $webhookDelivery): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $payment = $webhookDelivery->payment;

        if ($payment !== null && ! $this->ownershipScopeService->canActorAccessPayment($actor, $payment)) {
            return $this->errorResponse('Webhook delivery not found.', null, 404, 'webhook_delivery_not_found');
        }

        try {
            $delivery = $this->webhookDeliveryService->retry($webhookDelivery, $actor);
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: 'Webhook retry failed.',
                errors: ['code' => $exception->getMessage()],
                statusCode: 422,
            );
        }

        return $this->successResponse(
            data: (new WebhookDeliveryResource($delivery))->resolve(),
            message: 'Webhook delivery retry queued.',
        );
    }
}
