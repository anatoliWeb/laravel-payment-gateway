<?php

namespace App\Support\Billing;

/**
 * Central billing error code registry.
 *
 * WHY:
 * Billing responses already use stable machine-readable codes such as
 * `idempotency_key_conflict`, `insufficient_wallet_balance`, and
 * `payment_method_not_found`. Keeping the catalog in one place makes the
 * public API contract explicit without forcing a broad service rewrite.
 *
 * SECURITY:
 * The catalog only stores safe public codes, HTTP status codes, and short
 * human-readable messages. It must never contain provider secrets, raw
 * idempotency keys, card details, or internal stack traces.
 */
final class BillingErrorCatalog
{
    /**
     * @var array<string, array{status: int, message: string}>
     */
    private const MAP = [
        'payment_already_processed' => ['status' => 409, 'message' => 'Payment already processed.'],
        'invalid_payment_state' => ['status' => 422, 'message' => 'Invalid payment state.'],
        'idempotency_key_required' => ['status' => 422, 'message' => 'Idempotency key is required.'],
        'idempotency_key_conflict' => ['status' => 422, 'message' => 'Idempotency key conflict.'],
        'idempotency_conflict' => ['status' => 422, 'message' => 'Idempotency key conflict.'],
        'idempotency_request_processing' => ['status' => 409, 'message' => 'Idempotency request is already processing.'],
        'idempotency_replay_resource_missing' => ['status' => 409, 'message' => 'Original resource is no longer available.'],
        'payment_not_found' => ['status' => 404, 'message' => 'Payment not found.'],
        'subscription_inactive' => ['status' => 422, 'message' => 'Subscription is inactive.'],
        'subscription_not_found' => ['status' => 404, 'message' => 'Subscription not found.'],
        'subscription_status_is_final' => ['status' => 422, 'message' => 'Subscription status is final.'],
        'subscription_activation_requires_succeeded_payment' => ['status' => 422, 'message' => 'Subscription activation requires a succeeded payment.'],
        'payment_preference_invalid' => ['status' => 422, 'message' => 'Payment preference is invalid.'],
        'feature_limit_exceeded' => ['status' => 403, 'message' => 'Feature limit exceeded.'],
        'feature_blocked' => ['status' => 403, 'message' => 'Feature is blocked.'],
        'feature_override_disabled' => ['status' => 403, 'message' => 'Feature override is disabled.'],
        'feature_not_available' => ['status' => 403, 'message' => 'Feature is not available.'],
        'unsupported_period' => ['status' => 422, 'message' => 'Unsupported billing period.'],
        'insufficient_wallet_balance' => ['status' => 422, 'message' => 'Insufficient wallet balance.'],
        'insufficient_held_wallet_balance' => ['status' => 422, 'message' => 'Insufficient held wallet balance.'],
        'duplicate_wallet_debit' => ['status' => 409, 'message' => 'Duplicate wallet debit detected.'],
        'auto_charge_consent_required' => ['status' => 422, 'message' => 'Auto charge consent is required.'],
        'wallet_amount_must_be_positive' => ['status' => 422, 'message' => 'Wallet amount must be positive.'],
        'wallet_adjustment_reason_required' => ['status' => 422, 'message' => 'Wallet adjustment reason is required.'],
        'wallet_currency_not_available' => ['status' => 422, 'message' => 'Wallet currency is not available.'],
        'payment_method_not_found' => ['status' => 422, 'message' => 'Payment method not found.'],
        'payment_method_not_allowed' => ['status' => 422, 'message' => 'Payment method is not allowed.'],
        'payment_method_does_not_belong_to_user' => ['status' => 422, 'message' => 'Payment method does not belong to user.'],
        'payment_method_type_not_supported' => ['status' => 422, 'message' => 'Payment method type is not supported.'],
        'raw_card_data_not_allowed' => ['status' => 422, 'message' => 'Raw card data is not allowed.'],
        'payment_source_not_available' => ['status' => 422, 'message' => 'Payment source is not available.'],
        'invalid_payment_source' => ['status' => 422, 'message' => 'Invalid payment source.'],
        'invalid_payment_strategy' => ['status' => 422, 'message' => 'Invalid payment strategy.'],
        'payment_currency_conflict' => ['status' => 422, 'message' => 'Payment currency conflict.'],
        'payment_currency_not_available' => ['status' => 422, 'message' => 'Payment currency is not available.'],
        'payment_amount_conflict' => ['status' => 422, 'message' => 'Payment amount conflict.'],
        'payment_amount_must_be_positive' => ['status' => 422, 'message' => 'Payment amount must be positive.'],
        'payment_not_linked_to_invoice' => ['status' => 422, 'message' => 'Payment is not linked to invoice.'],
        'manual_invoice_not_supported' => ['status' => 422, 'message' => 'Manual invoice payments are not supported.'],
        'provider_not_configured' => ['status' => 503, 'message' => 'Provider is not configured.'],
        'provider_disabled' => ['status' => 503, 'message' => 'Provider is disabled.'],
        'provider_credentials_invalid' => ['status' => 503, 'message' => 'Provider credentials are invalid.'],
        'provider_account_not_found' => ['status' => 404, 'message' => 'Provider account not found.'],
        'provider_account_forbidden' => ['status' => 403, 'message' => 'Provider account is forbidden.'],
        'provider_account_not_accessible' => ['status' => 403, 'message' => 'Provider account is not accessible.'],
        'provider_charge_failed' => ['status' => 502, 'message' => 'Provider charge failed.'],
        'provider_timeout' => ['status' => 503, 'message' => 'Provider request timed out.'],
        'provider_webhook_signature_invalid' => ['status' => 403, 'message' => 'Provider webhook signature is invalid.'],
        'provider_unsupported_operation' => ['status' => 422, 'message' => 'Provider does not support this operation.'],
        'webhook_event_not_supported' => ['status' => 422, 'message' => 'Webhook event is not supported.'],
        'webhook_retry_not_allowed' => ['status' => 422, 'message' => 'Webhook retry is not allowed.'],
        'payment_already_final' => ['status' => 422, 'message' => 'Payment is already final.'],
        'payment_invalid_transition' => ['status' => 422, 'message' => 'Payment transition is invalid.'],
        'payment_not_simulatable' => ['status' => 422, 'message' => 'Payment is not simulatable.'],
        'invoice_items_required' => ['status' => 422, 'message' => 'Invoice items are required.'],
        'invoice_not_found' => ['status' => 404, 'message' => 'Invoice not found.'],
        'invoice_cannot_issue_empty' => ['status' => 422, 'message' => 'Invoice cannot be issued empty.'],
        'invoice_has_no_due_amount' => ['status' => 422, 'message' => 'Invoice has no due amount.'],
        'invoice_payment_not_allowed' => ['status' => 422, 'message' => 'Invoice payment is not allowed.'],
        'invoice_payment_currency_mismatch' => ['status' => 422, 'message' => 'Invoice payment currency mismatch.'],
        'invoice_status_is_final' => ['status' => 422, 'message' => 'Invoice status is final.'],
        'invalid_invoice_status_transition' => ['status' => 422, 'message' => 'Invoice status transition is invalid.'],
        'invoice_item_unit_amount_invalid' => ['status' => 422, 'message' => 'Invoice item unit amount is invalid.'],
        'payment_ownership_scope_conflict' => ['status' => 403, 'message' => 'Payment ownership scope conflict.'],
        'payer_not_linked_to_seller' => ['status' => 422, 'message' => 'Payer is not linked to seller.'],
        'company_not_found' => ['status' => 404, 'message' => 'Company not found.'],
        'company_not_active' => ['status' => 422, 'message' => 'Company is not active.'],
        'seller_not_found' => ['status' => 404, 'message' => 'Seller not found.'],
        'seller_not_active' => ['status' => 422, 'message' => 'Seller is not active.'],
        'plan_not_available' => ['status' => 404, 'message' => 'Plan is not available.'],
        'risk_check_failed' => ['status' => 422, 'message' => 'Risk check failed.'],
    ];

    /**
     * Normalize a raw exception code into a stable catalog value.
     */
    public static function normalizeCode(?string $code): string
    {
        $normalized = is_string($code) ? trim($code) : '';

        if ($normalized === '') {
            return 'request_failed';
        }

        return array_key_exists($normalized, self::MAP)
            ? $normalized
            : $normalized;
    }

    /**
     * Get the documented HTTP status for a billing code.
     */
    public static function statusFor(string $code): int
    {
        return self::MAP[$code]['status'] ?? 422;
    }

    /**
     * Get the short safe public message for a billing code.
     */
    public static function messageFor(string $code): string
    {
        return self::MAP[$code]['message'] ?? 'Request failed';
    }

    /**
     * Get the list of known billing error codes.
     *
     * @return array<int, string>
     */
    public static function knownCodes(): array
    {
        return array_keys(self::MAP);
    }
}
