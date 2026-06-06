# Wallet/Card Payment API Interface

## Purpose

Phase 13.3 exposes the runtime API surface for wallet balance, wallet top-up, saved simulator payment methods, payment preferences, and payment creation with `payment_source`.

The API is simulator-safe and uses the existing service layer instead of embedding billing logic in controllers.

Phase 17 adds invoice payment creation through `POST /api/v1/billing/invoices/{invoice}/pay`. The generated payment amount equals invoice `due_amount`, currency must match the invoice, and the invoice moves to `payment_pending` until a later safe payment-success transition marks it paid.

Phase 19 links successful payments to subscription activation and renewal through [Subscription Lifecycle](./subscription-lifecycle.md).

## Non-Goals

This phase does not implement:
- real Stripe, PayPal, LiqPay, WayForPay, or bank provider calls
- provider abstraction
- full idempotency replay/conflict storage
- payment success/failure simulation state machine
- webhook delivery
- real provider subscription activation or renewal outside the simulator lifecycle
- frontend screens

## Authentication

All Phase 13.3 endpoints live under `/api/v1/billing` and require `auth:sanctum`.

Responses use the shared API envelope:

```json
{
  "success": true,
  "message": "Request successful.",
  "data": {}
}
```

Domain failures return stable error codes under `errors.code`.

## Wallet API

### `GET /api/v1/billing/wallet`

Returns the current user's wallet:
- `uuid`
- `status`
- balances summary
- `created_at`

If the wallet does not exist, `WalletService::getOrCreateWallet()` creates it.

### `GET /api/v1/billing/wallet/balances`

Returns current wallet balances:
- currency code/name/symbol/precision
- `available_amount`
- `held_amount`
- `updated_at`

Amounts are stored in minor currency units.

### `GET /api/v1/billing/wallet/transactions`

Returns paginated wallet ledger entries:
- `uuid`
- type/direction/status
- amount/currency
- before/after balance snapshots
- reason
- linked payment UUID when available
- safe metadata only

The response does not expose raw idempotency keys.

### Internal wallet debit endpoint

No public `POST /api/v1/billing/wallet/debits` endpoint is exposed in Phase 13.3.

Wallet debits are allowed through payment creation with `payment_source=wallet`, where `PaymentService` validates wallet balance, creates the payment, links the wallet transaction, and preserves idempotency through the existing wallet transaction guard.

Permission-gated manual credits and debits use a separate auditable billing operation documented in [Wallet Adjustments API](./wallet-adjustments.md). Authentication alone does not grant access, and the operation does not create payments or replace the user payment flow.

## Wallet Top-Up

### `POST /api/v1/billing/wallet/top-ups`

Headers:
- `Idempotency-Key`: required

Body:

```json
{
  "amount": 3000,
  "currency": "USD",
  "payment_method_id": 1,
  "metadata": {
    "source": "billing_settings"
  }
}
```

Behavior:
1. creates a simulator-safe payment-method payment through `PaymentService`
2. credits wallet balance through `WalletTransactionService`
3. stores wallet transaction metadata with `source = manual_wallet_top_up`
4. writes `billing.wallet_top_up_succeeded` activity log

Manual top-up intentionally does not use `AutoTopUpService`; automation threshold, consent, and limits are not part of a user-requested top-up.

## Payment Methods API

### `GET /api/v1/billing/payment-methods`

Returns only the authenticated user's payment methods.

### `POST /api/v1/billing/payment-methods`

Supported types:
- `fake_card`
- `fake_manual_invoice`
- `fake_wallet`

Raw card fields are rejected:
- `card_number`
- `number`
- `pan`
- `cvv`
- `cvc`
- `security_code`

Only simulator-safe display fields are stored.

### `PATCH /api/v1/billing/payment-methods/{paymentMethod}`

Allowed updates:
- `display_name`
- `status`
- safe `metadata`

The API does not allow changing `user_id`, provider, provider reference, card-like routing fields, or ownership.

### `DELETE /api/v1/billing/payment-methods/{paymentMethod}`

Soft delete behavior:
- sets status to `inactive`
- clears `is_default`
- clears default preference if needed

### `POST /api/v1/billing/payment-methods/{paymentMethod}/set-default`

The method must belong to the user and be active.

Setting a default method synchronizes:
- `payment_methods.is_default`
- `user_payment_preferences.default_payment_method_id`

## Payment Preferences API

### `GET /api/v1/billing/payment-preferences`

Returns:
- strategy
- default payment method
- auto charge status and consent timestamp
- auto top-up status, consent timestamp, threshold, amount, currency, and limits

If preferences do not exist, `PaymentPreferenceService::getOrCreatePreferences()` creates the default row.

### `PATCH /api/v1/billing/payment-preferences`

Allowed updates:
- `strategy`
- `default_payment_method_id`
- `auto_charge_enabled`
- `auto_top_up_enabled`
- `auto_top_up_threshold_amount`
- `auto_top_up_amount`
- `auto_top_up_currency`
- `max_auto_top_up_per_day`
- `max_auto_top_up_per_month`

Enabling auto charge sets `auto_charge_consent_at` when missing and writes `billing.auto_charge_consent_changed`.

Preferences update does not trigger a payment, top-up, or subscription activation.

## Payment Creation With payment_source

Existing endpoint:

### `POST /api/v1/billing/payments`

Supported sources:
- `wallet`
- `payment_method`
- `wallet_first`

`Idempotency-Key` is required.

Optional ownership context:
- `company_id`
- `seller_id`

These fields are shape-validated by the FormRequest and business-validated by `OwnershipScopeService`. Seller scope infers its parent company. Existing requests without either field remain user-scoped.

## Payment Strategies

`user_payment_preferences.strategy` can be:
- `wallet_only`
- `payment_method_only`
- `wallet_first`
- `manual_invoice`

When `payment_source` is omitted, `PaymentService` resolves the source from preference strategy or available default method/wallet balance.

## Payment Simulation API

### `POST /api/v1/billing/payments/{payment}/simulate/success`

Requires `billing.payments.simulate`.

Simulates a demo-safe payment transition from `pending` or `processing` to `succeeded`.

### `POST /api/v1/billing/payments/{payment}/simulate/failure`

Requires `billing.payments.simulate`.

Body:

```json
{
  "reason": "card_declined",
  "metadata": {
    "scenario": "demo_decline"
  }
}
```

Simulates a demo-safe payment transition from `pending` or `processing` to `failed`.

Simulation behavior is documented in [Payment Simulation Flow](./payment-simulation.md).

## Webhook Delivery API

### `GET /api/v1/billing/payments/{payment}/webhooks`

Requires `billing.webhooks.view`.

Returns safe outbound webhook delivery history for a payment. Full callback URLs, signatures, and secrets are not exposed.

### `POST /api/v1/billing/webhooks/{webhookDelivery}/retry`

Requires `billing.webhooks.retry`.

Retries failed, retrying, or permanently failed outbound delivery records. Delivered, pending, and processing records return `webhook_retry_not_allowed`.

Webhook behavior is documented in [Webhook Delivery Flow](./webhooks.md).

## Idempotency Requirements

Required now:
- `POST /api/v1/billing/payments`
- `POST /api/v1/billing/wallet/top-ups`
- `POST /api/v1/billing/wallet-adjustments`

Phase 14 adds central request replay/conflict storage. Payment creation and wallet top-up now store user-scoped, hashed idempotency keys and deterministic safe payload fingerprints.

The local wallet transaction guard remains as a second ledger-level protection.

## Stable Error Codes

Current stable domain codes include:
- `idempotency_key_required`
- `insufficient_wallet_balance`
- `payment_method_not_found`
- `payment_method_not_allowed`
- `payment_method_does_not_belong_to_user`
- `invalid_payment_strategy`
- `payment_currency_not_available`
- `payment_risk_blocked`
- `auto_charge_consent_missing`
- `auto_top_up_currency_not_available`
- `invalid_auto_top_up_amount`
- `company_not_found`
- `company_not_active`
- `seller_not_found`
- `seller_not_active`
- `payment_ownership_scope_conflict`
- `payment_not_simulatable`
- `payment_invalid_transition`
- `payment_already_final`
- `webhook_event_not_supported`
- `webhook_retry_not_allowed`

## Security and Data Safety

Rules:
- no raw card data accepted
- no CVV/CVC/security code accepted
- no provider secrets returned
- payment method resources expose last4 only
- wallet transaction resources hide raw idempotency keys
- metadata is validated and output is whitelisted
- raw idempotency keys are hashed before registry storage

## Simulator-Only Behavior

Payment-method top-ups create simulator-safe payment records. No external provider is called.

Wallet payments debit internal balance and create an internal succeeded payment.

Payment-method payments remain simulator attempts and do not activate subscriptions.

## Testing Strategy

Phase 13.3 API tests cover:
- wallet overview/balances/transactions
- manual wallet top-up
- payment method CRUD/default/deactivation
- payment preference reads/updates/consent activity
- wallet/card/wallet-first payment source behavior
- stable error responses

## Status

Phase 13.3 implements the Wallet/Card Payment API Interface foundation.

Provider adapter/config readiness is documented in [External Payment Provider Integration Readiness](./payment-providers.md).

Central replay, conflict, processing, and expiration behavior is documented in [Idempotency Support](./idempotency.md).

Company/seller payment ownership behavior is documented in [Company / Seller Ownership Scope](./ownership-scope.md).
