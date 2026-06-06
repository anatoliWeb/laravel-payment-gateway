<?php

namespace App\Listeners\Billing;

use App\Events\Billing\PaymentSucceeded;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionLifecycleService;
use Throwable;

/**
 * Activates linked subscriptions only after a persisted successful payment.
 */
class ActivateSubscriptionAfterPaymentSucceeded
{
    public function __construct(
        private readonly SubscriptionLifecycleService $subscriptionLifecycleService,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment->fresh(['invoice']);
        $subscriptionId = $payment?->subscription_id ?? $payment?->invoice?->subscription_id;

        if (! $payment || ! $subscriptionId) {
            return;
        }

        $subscription = Subscription::query()->find($subscriptionId);
        if (! $subscription) {
            return;
        }

        try {
            // WHY: Payment success is the only automatic activation signal;
            // failed, pending, expired, or cancelled payments must never grant access.
            $this->subscriptionLifecycleService->activateAfterPayment($subscription, $payment);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
