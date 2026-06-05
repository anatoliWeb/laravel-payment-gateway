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
            return $this->errorResponse('Payment not found.', null, 404);
        }

        $perPage = max(1, min((int) $request->query('per_page', 25), 100));
        $paginator = WebhookDelivery::query()
            ->where('payment_id', $payment->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (WebhookDelivery $delivery) => (new WebhookDeliveryResource($delivery))->resolve())
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'Request successful',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function retry(Request $request, WebhookDelivery $webhookDelivery): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $payment = $webhookDelivery->payment;

        if ($payment !== null && ! $this->ownershipScopeService->canActorAccessPayment($actor, $payment)) {
            return $this->errorResponse('Webhook delivery not found.', null, 404);
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
