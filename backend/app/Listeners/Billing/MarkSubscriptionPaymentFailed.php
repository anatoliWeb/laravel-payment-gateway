<?php

namespace App\Listeners\Billing;

use App\Events\Billing\PaymentFailed;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionLifecycleService;
use Throwable;

/**
 * Records failed subscription payment outcomes without granting access.
 */
class MarkSubscriptionPaymentFailed
{
    public function __construct(
        private readonly SubscriptionLifecycleService $subscriptionLifecycleService,
    ) {}

    public function handle(PaymentFailed $event): void
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
            // WHY: Failure handling is intentionally conservative: initial
            // pending subscriptions stay inactive and renewal failures become recoverable.
            $this->subscriptionLifecycleService->markPaymentFailed($subscription, $payment);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
