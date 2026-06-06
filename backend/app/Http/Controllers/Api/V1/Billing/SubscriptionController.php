<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\DTO\Payments\CreatePaymentData;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Billing\CancelSubscriptionRequest;
use App\Http\Requests\Api\V1\Billing\ChangeSubscriptionPlanRequest;
use App\Http\Requests\Api\V1\Billing\StoreSubscriptionRequest;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Http\Resources\Payments\PaymentResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionLifecycleService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SubscriptionController extends BaseController
{
    public function __construct(
        private readonly SubscriptionLifecycleService $subscriptionLifecycleService,
        private readonly PaymentService $paymentService,
    ) {}

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $plan = $this->resolvePlan($request->integer('plan_id') ?: null, $request->input('plan_slug'));
            $subscription = $this->subscriptionLifecycleService->createPendingSubscription($user, $plan, [
                'idempotency_key' => (string) $request->header('Idempotency-Key'),
                'source' => 'subscription_api',
                'metadata' => array_merge((array) $request->input('metadata', []), [
                    'auto_renew' => $request->boolean('auto_renew'),
                ]),
            ]);

            $payment = null;
            if ((int) $plan->price_amount > 0) {
                $payment = $this->paymentService->createPayment(new CreatePaymentData(
                    user: $user,
                    subscriptionId: $subscription->id,
                    planSlug: null,
                    amount: (int) $plan->price_amount,
                    currency: strtoupper($plan->currency),
                    paymentSource: $request->input('payment_source'),
                    paymentStrategy: $request->input('payment_strategy'),
                    paymentMethodId: $request->integer('payment_method_id') ?: null,
                    callbackUrl: $request->input('callback_url'),
                    description: 'Subscription initial payment',
                    metadata: [
                        'source' => 'subscription_api',
                        'subscription_initial_payment' => true,
                    ],
                    idempotencyKey: (string) $request->header('Idempotency-Key'),
                ));
            }
        } catch (RuntimeException $exception) {
            return $this->errorResponse('Subscription creation failed.', ['code' => $exception->getMessage()], 422);
        }

        return $this->successResponse([
            'subscription' => (new SubscriptionResource($subscription->refresh()->load('plan')))->resolve(),
            'payment' => $payment ? (new PaymentResource($payment))->resolve() : null,
        ], 'Subscription created successfully.', 201);
    }

    public function show(Subscription $subscription): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $this->canAccess($user, $subscription)) {
            return $this->errorResponse('Subscription not found.', ['code' => 'subscription_not_found'], 404);
        }

        return $this->successResponse((new SubscriptionResource($subscription->load('plan')))->resolve(), 'Subscription fetched successfully.');
    }

    public function changePlan(Subscription $subscription, ChangeSubscriptionPlanRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->canManage($user, $subscription)) {
            return $this->errorResponse('Subscription not found.', ['code' => 'subscription_not_found'], 404);
        }

        $newPlan = Plan::query()->active()->findOrFail($request->integer('plan_id'));
        $direction = $request->input('direction') ?? (
            (int) $newPlan->price_amount >= (int) $subscription->plan?->price_amount ? 'upgrade' : 'downgrade'
        );

        try {
            $subscription = $this->subscriptionLifecycleService->changePlan($subscription, $newPlan, $user, [
                'direction' => $direction,
                'apply_immediately' => $direction !== 'upgrade',
            ]);

            $payment = null;
            if (($direction ?? 'upgrade') === 'upgrade' && (int) $newPlan->price_amount > 0) {
                $payment = $this->paymentService->createPayment(new CreatePaymentData(
                    user: $subscription->user,
                    subscriptionId: $subscription->id,
                    planSlug: null,
                    amount: (int) $newPlan->price_amount,
                    currency: strtoupper($newPlan->currency),
                    paymentSource: $request->input('payment_source'),
                    paymentStrategy: null,
                    paymentMethodId: $request->integer('payment_method_id') ?: null,
                    callbackUrl: null,
                    description: 'Subscription plan upgrade payment',
                    metadata: [
                        'source' => 'subscription_api',
                        'plan_change' => 'upgrade',
                        'target_plan_id' => $newPlan->id,
                    ],
                    idempotencyKey: (string) $request->header('Idempotency-Key'),
                ));
            }
        } catch (RuntimeException $exception) {
            return $this->errorResponse('Subscription plan change failed.', ['code' => $exception->getMessage()], 422);
        }

        return $this->successResponse([
            'subscription' => (new SubscriptionResource($subscription->refresh()->load('plan')))->resolve(),
            'payment' => $payment ? (new PaymentResource($payment))->resolve() : null,
        ], 'Subscription plan change accepted.');
    }

    public function cancel(Subscription $subscription, CancelSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->canManage($user, $subscription)) {
            return $this->errorResponse('Subscription not found.', ['code' => 'subscription_not_found'], 404);
        }

        try {
            $subscription = $this->subscriptionLifecycleService->cancelSubscription(
                $subscription,
                $user,
                $request->input('reason'),
                $request->boolean('immediate'),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse('Subscription cancellation failed.', ['code' => $exception->getMessage()], 422);
        }

        return $this->successResponse((new SubscriptionResource($subscription->load('plan')))->resolve(), 'Subscription cancelled successfully.');
    }

    private function resolvePlan(?int $planId, ?string $planSlug): Plan
    {
        $query = Plan::query()->active();

        $plan = $planId !== null
            ? $query->find($planId)
            : $query->bySlug((string) $planSlug)->first();

        if (! $plan) {
            throw new RuntimeException('plan_not_available');
        }

        return $plan;
    }

    private function canAccess(User $user, Subscription $subscription): bool
    {
        return (int) $subscription->user_id === (int) $user->id
            || $user->isAdmin()
            || $user->hasPermission('billing.subscriptions.view');
    }

    private function canManage(User $user, Subscription $subscription): bool
    {
        return (int) $subscription->user_id === (int) $user->id
            || $user->isAdmin()
            || $user->hasPermission('billing.subscriptions.manage');
    }
}
