<?php

namespace App\Events\Billing\Support;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WalletTransaction;

trait SafeBillingEventPayload
{
    /**
     * Build a safe payment payload for listeners and queued post-event actions.
     */
    protected function paymentPayload(Payment $payment): array
    {
        return [
            'payment_id' => $payment->id,
            'payment_uuid' => $payment->uuid,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'payer_user_id' => $payment->payer_user_id ?? $payment->user_id,
            'company_id' => $payment->company_id,
            'seller_id' => $payment->seller_id,
            'subscription_id' => $payment->subscription_id,
            'invoice_id' => $payment->invoice_id,
            'provider' => $payment->provider,
            'payment_method' => $payment->payment_method,
        ];
    }

    /**
     * Build a safe invoice payload without raw metadata or idempotency data.
     */
    protected function invoicePayload(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id,
            'invoice_uuid' => $invoice->uuid,
            'invoice_number' => $invoice->number,
            'status' => $invoice->status,
            'total_amount' => $invoice->total_amount,
            'due_amount' => $invoice->due_amount,
            'currency' => $invoice->currency,
            'payer_user_id' => $invoice->payer_user_id,
            'company_id' => $invoice->company_id,
            'seller_id' => $invoice->seller_id,
            'subscription_id' => $invoice->subscription_id,
            'payment_id' => $invoice->payment_id,
        ];
    }

    /**
     * Build a safe wallet transaction payload for downstream placeholders.
     */
    protected function walletPayload(WalletTransaction $transaction): array
    {
        return [
            'wallet_transaction_id' => $transaction->id,
            'wallet_transaction_uuid' => $transaction->uuid,
            'wallet_id' => $transaction->wallet_id,
            'wallet_balance_id' => $transaction->wallet_balance_id,
            'user_id' => $transaction->wallet?->user_id,
            'currency_id' => $transaction->currency_id,
            'currency' => $transaction->currency?->code,
            'type' => $transaction->type,
            'direction' => $transaction->direction,
            'amount' => $transaction->amount,
            'payment_id' => $transaction->payment_id,
            'subscription_id' => $transaction->subscription_id,
        ];
    }
}
