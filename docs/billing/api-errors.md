# Billing API Errors

## Purpose

This document is the source of truth for billing API response envelopes and stable domain error codes.

It keeps the payment gateway simulator contract explicit without forcing a broad rewrite of existing billing services.

## Success Response

```json
{
  "success": true,
  "message": "Request successful.",
  "data": {},
  "meta": {}
}
```

Rules:
- `data` is always the primary payload for successful billing responses
- `meta` is optional and used for pagination or contextual metadata
- success responses do not include `code`

## Error Response

```json
{
  "success": false,
  "message": "Payment creation failed.",
  "code": "idempotency_key_conflict",
  "errors": {}
}
```

Rules:
- `code` is stable and machine-readable
- `message` is safe for end users and must not leak secrets
- `errors` is used for field-level validation payloads or safe domain details
- stack traces are never returned to API clients

## Validation Errors

Validation failures keep field-level detail:

```json
{
  "success": false,
  "message": "Validation failed",
  "code": "validation_failed",
  "errors": {
    "amount": [
      "The amount field is required."
    ]
  }
}
```

Validation responses must not expose raw card data, provider secrets, or raw idempotency keys.

## Domain Errors

Domain failures return a stable top-level `code` and may also include the same code under `errors.code` for backward compatibility.

Examples:
- `idempotency_key_conflict`
- `insufficient_wallet_balance`
- `payment_method_not_found`
- `payment_method_not_allowed`
- `subscription_inactive`
- `feature_limit_exceeded`
- `provider_not_configured`
- `provider_timeout`

## Error Code Catalog

The current catalog is implemented in `App\Support\Billing\BillingErrorCatalog`.

Documented codes include:
- `validation_failed`
- `unauthenticated`
- `forbidden`
- `endpoint_not_found`
- `resource_not_found`
- `payment_not_found`
- `payment_already_processed`
- `invalid_payment_state`
- `payment_already_final`
- `payment_invalid_transition`
- `payment_not_simulatable`
- `payment_source_not_available`
- `invalid_payment_source`
- `invalid_payment_strategy`
- `payment_method_not_found`
- `payment_method_not_allowed`
- `payment_method_does_not_belong_to_user`
- `payment_method_type_not_supported`
- `raw_card_data_not_allowed`
- `payment_preference_invalid`
- `subscription_inactive`
- `subscription_not_found`
- `subscription_status_is_final`
- `subscription_activation_requires_succeeded_payment`
- `feature_limit_exceeded`
- `feature_blocked`
- `feature_override_disabled`
- `feature_not_available`
- `unsupported_period`
- `insufficient_wallet_balance`
- `insufficient_held_wallet_balance`
- `duplicate_wallet_debit`
- `auto_charge_consent_required`
- `idempotency_key_required`
- `idempotency_key_conflict`
- `idempotency_request_processing`
- `idempotency_replay_resource_missing`
- `provider_not_configured`
- `provider_disabled`
- `provider_credentials_invalid`
- `provider_account_not_found`
- `provider_account_forbidden`
- `provider_account_not_accessible`
- `provider_charge_failed`
- `provider_timeout`
- `provider_webhook_signature_invalid`
- `provider_unsupported_operation`
- `webhook_retry_not_allowed`
- `wallet_amount_must_be_positive`
- `wallet_adjustment_reason_required`
- `wallet_currency_not_available`
- `payment_currency_conflict`
- `payment_amount_conflict`
- `payment_amount_must_be_positive`
- `payment_not_linked_to_invoice`
- `manual_invoice_not_supported`
- `invoice_items_required`
- `invoice_not_found`
- `invoice_cannot_issue_empty`
- `invoice_has_no_due_amount`
- `invoice_payment_not_allowed`
- `invoice_payment_currency_mismatch`
- `invoice_status_is_final`
- `invalid_invoice_status_transition`
- `invoice_item_unit_amount_invalid`
- `payment_ownership_scope_conflict`
- `payer_not_linked_to_seller`
- `company_not_found`
- `company_not_active`
- `seller_not_found`
- `seller_not_active`
- `plan_not_available`
- `risk_check_failed`

## Security Rules

- do not expose provider secrets
- do not expose raw idempotency keys
- do not expose raw card numbers, CVV/CVC, tokens, passwords, or private keys
- do not return stack traces in production
- do not use wildcard permissive codes for provider-specific failures

## Testing Strategy

Targeted tests should verify:
- success envelope shape
- validation envelope shape
- stable billing domain codes
- no secret or idempotency key leakage
- backward compatibility for `errors.code`
