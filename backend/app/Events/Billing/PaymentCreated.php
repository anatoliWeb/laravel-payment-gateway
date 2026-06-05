<?php

namespace App\Events\Billing;

use App\Events\Billing\Support\SafeBillingEventPayload;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised after a new payment attempt is persisted.
 */
class PaymentCreated
{
    use Dispatchable, SafeBillingEventPayload, SerializesModels;

    public array $payload;

    public function __construct(public readonly Payment $payment)
    {
        $this->payload = $this->paymentPayload($payment);
    }
}
