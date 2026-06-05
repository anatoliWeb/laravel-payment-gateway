<?php

namespace App\Events\Billing;

use App\Events\Billing\Support\SafeBillingEventPayload;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised after wallet available balance is decreased.
 */
class WalletDebited
{
    use Dispatchable, SafeBillingEventPayload, SerializesModels;

    public array $payload;

    public function __construct(public readonly WalletTransaction $transaction)
    {
        $this->payload = $this->walletPayload($transaction);
    }
}
