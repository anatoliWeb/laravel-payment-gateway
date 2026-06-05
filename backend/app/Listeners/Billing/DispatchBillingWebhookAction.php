<?php

namespace App\Listeners\Billing;

use Illuminate\Support\Facades\Log;

/**
 * Structural hook for future listener-owned billing webhook dispatch.
 */
class DispatchBillingWebhookAction
{
    public function handle(object $event): void
    {
        // WHY: Phase 16 already dispatches payment webhooks from PaymentSimulationService.
        // This listener exists as the migration boundary and intentionally avoids duplicate deliveries.
        Log::debug('Billing webhook listener placeholder skipped to avoid duplicate Phase 16 delivery', [
            'event' => $event::class,
            'payload' => $event->payload ?? [],
        ]);
    }
}
