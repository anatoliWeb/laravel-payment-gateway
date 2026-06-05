<?php

namespace App\Listeners\Billing;

use App\Jobs\Billing\GenerateBillingReceiptJob;

/**
 * Queues placeholder receipt/document generation for settlement-like events.
 */
class DispatchReceiptGenerationAction
{
    public function handle(object $event): void
    {
        GenerateBillingReceiptJob::dispatch($event::class, $event->payload ?? []);
    }
}
