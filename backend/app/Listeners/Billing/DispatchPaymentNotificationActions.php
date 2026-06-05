<?php

namespace App\Listeners\Billing;

use App\Jobs\Billing\SendBillingEmailNotificationJob;
use App\Jobs\Billing\SendBillingSmsNotificationJob;

/**
 * Queues placeholder payment notification actions from billing events.
 */
class DispatchPaymentNotificationActions
{
    public function handle(object $event): void
    {
        $payload = $event->payload ?? [];
        $eventName = $event::class;

        // WHY: Notification side effects are queued so payment state changes stay fast and rollback-safe.
        SendBillingSmsNotificationJob::dispatch($eventName, $payload);
        SendBillingEmailNotificationJob::dispatch($eventName, $payload);
    }
}
