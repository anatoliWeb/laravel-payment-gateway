<?php

namespace App\Jobs\Billing;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder extension point for future billing email notifications.
 */
class SendBillingEmailNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $eventName,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        // WHY: Email sending is intentionally decoupled from financial state transitions.
        Log::info('Billing email notification placeholder skipped', [
            'event' => $this->eventName,
            'payload' => $this->payload,
        ]);
    }
}
