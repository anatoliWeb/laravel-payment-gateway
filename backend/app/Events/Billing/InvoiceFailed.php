<?php

namespace App\Events\Billing;

use App\Events\Billing\Support\SafeBillingEventPayload;
use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised after an invoice payment attempt fails.
 */
class InvoiceFailed
{
    use Dispatchable, SafeBillingEventPayload, SerializesModels;

    public array $payload;

    public function __construct(public readonly Invoice $invoice)
    {
        $this->payload = $this->invoicePayload($invoice);
    }
}
