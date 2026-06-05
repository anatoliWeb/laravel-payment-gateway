<?php

namespace App\Listeners\Billing;

use App\Jobs\Billing\SendBillingEmailNotificationJob;
use App\Jobs\Billing\SendBillingSmsNotificationJob;

/**
 * Queues placeholder invoice notification actions from invoice events.
 */
class DispatchInvoiceNotificationActions
{
    public function handle(object $event): void
    {
        $payload = $event->payload ?? [];
        $eventName = $event::class;

        SendBillingSmsNotificationJob::dispatch($eventName, $payload);
        SendBillingEmailNotificationJob::dispatch($eventName, $payload);
    }
}
