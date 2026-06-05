<?php

namespace App\Jobs\Billing;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder extension point for future billing SMS notifications.
 */
class SendBillingSmsNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $eventName,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        // WHY: No real SMS provider is configured; queue boundary prevents future provider latency from blocking billing writes.
        Log::info('Billing SMS notification placeholder skipped', [
            'event' => $this->eventName,
            'payload' => $this->payload,
        ]);
    }
}
