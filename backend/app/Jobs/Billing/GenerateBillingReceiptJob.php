<?php

namespace App\Jobs\Billing;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder extension point for future receipt or document generation.
 */
class GenerateBillingReceiptJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $eventName,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        // WHY: Document generation must not live in payment/invoice services; this no-op keeps the boundary ready.
        Log::info('Billing receipt generation placeholder skipped', [
            'event' => $this->eventName,
            'payload' => $this->payload,
        ]);
    }
}
