<?php

namespace App\Listeners\Billing;

use App\Jobs\Billing\NotifySellerCompanyBillingEventJob;

/**
 * Queues placeholder seller/company notifications for scoped billing events.
 */
class DispatchSellerCompanyNotificationAction
{
    public function handle(object $event): void
    {
        $payload = $event->payload ?? [];

        if (($payload['company_id'] ?? null) === null && ($payload['seller_id'] ?? null) === null) {
            return;
        }

        NotifySellerCompanyBillingEventJob::dispatch($event::class, $payload);
    }
}
