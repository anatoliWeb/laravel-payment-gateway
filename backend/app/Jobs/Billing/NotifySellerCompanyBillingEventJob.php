<?php

namespace App\Jobs\Billing;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder extension point for future seller/company billing notifications.
 */
class NotifySellerCompanyBillingEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $eventName,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        // WHY: Merchant notifications are side effects and must not change payment or invoice atomicity.
        Log::info('Seller/company billing notification placeholder skipped', [
            'event' => $this->eventName,
            'payload' => $this->payload,
        ]);
    }
}
