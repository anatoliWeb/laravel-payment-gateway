# Billing Runtime API

## Purpose

This document describes the implemented billing runtime API under `/api/v1/billing`.

It is the source of truth for current request/response examples, simulator-safe payment flows, wallet operations, invoices, subscriptions, webhooks, and idempotency behavior.

## Contract Rules

- JSON only
- authenticated via `auth:sanctum`
- stable success/error envelope
- errors return a machine-readable `code`
- write endpoints that create money-moving side effects use `Idempotency-Key`
- no raw card data, CVV/CVC, tokens, passwords, or provider secrets

## Common Response Envelope

Success:

```json
{
  "success": true,
  "message": "Request successful.",
  "data": {},
  "meta": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Request failed.",
  "code": "validation_failed",
  "errors": {}
}
```

The stable error catalog is documented in [Billing API Errors](./api-errors.md).

## Current Routes

### Wallet

- `GET /api/v1/billing/wallet`
- `GET /api/v1/billing/wallet/balances`
- `GET /api/v1/billing/wallet/transactions`
- `POST /api/v1/billing/wallet/top-ups`

### Payment Methods and Preferences

- `GET /api/v1/billing/payment-methods`
- `POST /api/v1/billing/payment-methods`
- `PUT|PATCH /api/v1/billing/payment-methods/{paymentMethod}`
- `DELETE /api/v1/billing/payment-methods/{paymentMethod}`
- `POST /api/v1/billing/payment-methods/{paymentMethod}/set-default`
- `GET /api/v1/billing/payment-preferences`
- `PATCH /api/v1/billing/payment-preferences`

### Payments

- `POST /api/v1/billing/payments`
- `POST /api/v1/billing/payments/{payment}/simulate/success`
- `POST /api/v1/billing/payments/{payment}/simulate/failure`
- `GET /api/v1/billing/payments/{payment}/webhooks`

### Subscriptions

- `POST /api/v1/billing/subscriptions`
- `GET /api/v1/billing/subscriptions/{subscription}`
- `POST /api/v1/billing/subscriptions/{subscription}/change-plan`
- `POST /api/v1/billing/subscriptions/{subscription}/cancel`

### Invoices

- `GET /api/v1/billing/invoices`
- `POST /api/v1/billing/invoices`
- `GET /api/v1/billing/invoices/{invoice}`
- `POST /api/v1/billing/invoices/{invoice}/issue`
- `POST /api/v1/billing/invoices/{invoice}/void`
- `POST /api/v1/billing/invoices/{invoice}/pay`

### Webhook Retry

- `POST /api/v1/billing/webhooks/{webhookDelivery}/retry`

### Manual Wallet Adjustment

- `POST /api/v1/billing/wallet-adjustments`

## Admin Billing API

The admin billing API is read-only for operational review except for permission-gated simulator actions and manual wallet adjustments documented elsewhere.

All admin read surfaces require backend permissions; frontend checks are UX-only.

### Payments

- `GET /api/v1/billing/admin/payments`
- `GET /api/v1/billing/admin/payments/{payment}`
- `GET /api/v1/billing/admin/payments/{payment}/transactions`

Permissions:

- `billing.payments.view_any`
- `billing.payments.view_transactions`

### Subscriptions

- `GET /api/v1/billing/admin/subscriptions`
- `GET /api/v1/billing/admin/subscriptions/{subscription}`

Permissions:

- `billing.subscriptions.view_any`

### Wallets

- `GET /api/v1/billing/admin/wallets`
- `GET /api/v1/billing/admin/wallets/{wallet}`
- `GET /api/v1/billing/admin/wallets/{wallet}/transactions`

Permissions:

- `billing.wallets.view_any`
- `billing.wallets.view_transactions`

### Idempotency Records

- `GET /api/v1/billing/admin/idempotency-keys`
- `GET /api/v1/billing/admin/idempotency-keys/{idempotencyKey}`

Permissions:

- `billing.idempotency.view_any`

### Provider Accounts

- `GET /api/v1/billing/admin/provider-accounts`
- `GET /api/v1/billing/admin/provider-accounts/{providerAccount}`

Permissions:

- `billing.provider_accounts.view_any`

### Restrictions / Blacklist

- `GET /api/v1/billing/admin/restrictions`
- `GET /api/v1/billing/admin/restrictions/{billingRestriction}`

Permissions:

- `billing.restrictions.view_any`

### Feature Overrides

- `GET /api/v1/billing/admin/overrides`
- `GET /api/v1/billing/admin/overrides/{featureOverride}`

Permissions:

- `billing.overrides.view_any`

### Reports

- `GET /api/v1/billing/admin/reports/revenue-summary`
- `GET /api/v1/billing/admin/reports/payment-status-summary`
- `GET /api/v1/billing/admin/reports/revenue-by-plan`
- `GET /api/v1/billing/admin/reports/revenue-by-currency`
- `GET /api/v1/billing/admin/reports/revenue-by-seller-company`
- `GET /api/v1/billing/admin/reports/subscription-metrics`
- `GET /api/v1/billing/admin/reports/invoice-metrics`
- `GET /api/v1/billing/admin/reports/wallet-metrics`

Permissions:

- `billing.reports.view`
- `billing.reports.view_financials` for revenue / money-bearing endpoints

Detailed request and response examples are documented in [Billing Reports API](./reports-api.md).

### Safety Notes

- safe fields only
- no raw idempotency keys
- no provider secrets
- no mutation endpoints for these read surfaces in this phase

## Payment Creation

### `POST /api/v1/billing/payments`

Headers:

- `Authorization: Bearer {{TOKEN}}`
- `Idempotency-Key: {{KEY}}`

Body:

```json
{
  "subscription_id": 123,
  "company_id": 15,
  "seller_id": 42,
  "plan_slug": "pro",
  "amount": 19900,
  "currency": "USD",
  "payment_source": "wallet_first",
  "payment_strategy": "wallet_first",
  "payment_method_id": 77,
  "callback_url": "https://example.test/billing/callback",
  "description": "Pro plan purchase",
  "metadata": {
    "source": "checkout"
  }
}
```

Accepted source values:

- `wallet`
- `payment_method`
- `wallet_first`

Accepted strategy values:

- `wallet_only`
- `payment_method_only`
- `wallet_first`
- `manual_invoice`

Notes:

- the service resolves ownership context when `company_id` or `seller_id` is present
- `amount` is required when `subscription_id` and `plan_slug` are both absent
- simulator payments do not call real providers
- successful create returns `201`
- idempotent replay returns the previously created result

## Subscription Lifecycle

### `POST /api/v1/billing/subscriptions`

Headers:

- `Authorization: Bearer {{TOKEN}}`
- `Idempotency-Key: {{KEY}}`

Body:

```json
{
  "plan_slug": "pro",
  "payment_source": "payment_method",
  "payment_strategy": "payment_method_only",
  "payment_method_id": 77,
  "callback_url": "https://example.test/billing/callback",
  "auto_renew": true,
  "metadata": {
    "source": "plan_select"
  }
}
```

Rules:

- `plan_id` or `plan_slug` is required
- free plans can become active immediately
- paid plans create a pending subscription and a linked payment
- the subscription becomes active only after payment success

### `POST /api/v1/billing/subscriptions/{subscription}/change-plan`

Body:

```json
{
  "plan_id": 2,
  "direction": "upgrade",
  "payment_source": "payment_method",
  "payment_method_id": 77,
  "metadata": {
    "source": "upgrade_flow"
  }
}
```

### `POST /api/v1/billing/subscriptions/{subscription}/cancel`

Body:

```json
{
  "reason": "customer_request",
  "immediate": false
}
```

## Invoice Lifecycle

### `POST /api/v1/billing/invoices`

Body:

```json
{
  "payer_user_id": 12,
  "company_id": 15,
  "seller_id": 42,
  "subscription_id": 123,
  "currency": "USD",
  "description": "Consulting bundle",
  "due_at": "2026-07-01T00:00:00Z",
  "metadata": {
    "source": "admin_issue"
  },
  "items": [
    {
      "item_type": "service",
      "description": "Implementation work",
      "quantity": 1,
      "unit_amount": 19900,
      "discount_amount": 0,
      "tax_amount": 0,
      "metadata": {
        "month": "2026-06"
      }
    }
  ]
}
```

### `POST /api/v1/billing/invoices/{invoice}/pay`

Body:

```json
{
  "payment_source": "wallet_first",
  "payment_strategy": "wallet_first",
  "payment_method_id": 77,
  "currency": "USD",
  "callback_url": "https://example.test/billing/callback",
  "description": "Invoice payment",
  "metadata": {
    "source": "invoice_pay"
  }
}
```

The generated payment amount equals the invoice due amount and the currency must match the invoice.

## Wallet API

### `GET /api/v1/billing/wallet`

Returns the current user's wallet and balances.

### `GET /api/v1/billing/wallet/balances`

Returns the wallet balance collection by currency.

### `GET /api/v1/billing/wallet/transactions`

Returns wallet ledger history with pagination.

### `POST /api/v1/billing/wallet/top-ups`

Headers:

- `Authorization: Bearer {{TOKEN}}`
- `Idempotency-Key: {{KEY}}`

Body:

```json
{
  "amount": 3000,
  "currency": "USD",
  "payment_method_id": 77,
  "metadata": {
    "source": "wallet_settings"
  }
}
```

This creates a simulator-safe payment and then credits the wallet.

## Payment Methods

### `POST /api/v1/billing/payment-methods`

Body:

```json
{
  "type": "fake_card",
  "brand": "Visa",
  "last4": "4242",
  "exp_month": 12,
  "exp_year": 2030,
  "display_name": "Visa ending 4242",
  "metadata": {
    "source": "checkout"
  }
}
```

Supported types:

- `fake_card`
- `fake_manual_invoice`
- `fake_wallet`

### `POST /api/v1/billing/payment-methods/{paymentMethod}/set-default`

Marks the method as the default saved payment method.

### `PATCH /api/v1/billing/payment-preferences`

Body:

```json
{
  "strategy": "wallet_first",
  "default_payment_method_id": 77,
  "auto_charge_enabled": true,
  "auto_top_up_enabled": true,
  "auto_top_up_threshold_amount": 500,
  "auto_top_up_amount": 3000,
  "auto_top_up_currency": "USD",
  "max_auto_top_up_per_day": 2,
  "max_auto_top_up_per_month": 10
}
```

This updates preference state only. It does not execute a charge by itself.

## Payment Simulation

### `POST /api/v1/billing/payments/{payment}/simulate/success`

Body:

```json
{
  "metadata": {
    "scenario": "demo_success"
  }
}
```

### `POST /api/v1/billing/payments/{payment}/simulate/failure`

Body:

```json
{
  "reason": "card_declined",
  "metadata": {
    "scenario": "demo_decline"
  }
}
```

Simulation endpoints are permission-gated and only exist for demo/operator flows.

## Webhook Delivery

### `GET /api/v1/billing/payments/{payment}/webhooks`

Returns safe webhook delivery history for a payment.

### `POST /api/v1/billing/webhooks/{webhookDelivery}/retry`

Retries a failed or retry-eligible outbound delivery.

## Manual Wallet Adjustment

### `POST /api/v1/billing/wallet-adjustments`

Headers:

- `Authorization: Bearer {{TOKEN}}`
- `Idempotency-Key: {{KEY}}`

Body:

```json
{
  "user_id": 42,
  "currency": "USD",
  "amount": 2500,
  "direction": "credit",
  "reason": "Support-approved correction",
  "description": "Billing reconciliation adjustment.",
  "reference": "ticket-1001",
  "metadata": {
    "case_type": "support"
  }
}
```

This route is permission-gated and writes an append-only wallet ledger transaction.

## Example Flows

### Example Payment Flow

1. `POST /api/v1/billing/payments`
2. payment is created in `pending` or `processing`
3. operator simulates success or failure
4. webhook delivery is queued when a callback URL is present
5. transaction history and activity logs are written

### Example Subscription Flow

1. `POST /api/v1/billing/subscriptions`
2. pending subscription is created
3. linked payment is created for paid plans
4. simulator success activates the subscription
5. scheduler later handles expiration or renewal checks

### Example Wallet First Flow

1. client sends `payment_source=wallet_first`
2. service tries wallet balance first
3. if wallet is insufficient, it falls back to the saved payment method
4. the final payment outcome is still simulator-safe

### Example Chat Limit Flow

Chat is not a billing route, but billing drives the limit.

When a user exceeds a paid or free chat quota, the chat API returns `403` with stable code `feature_limit_exceeded`.

Example route:

- `POST /api/v1/chat/conversations/{conversation}/messages`

Example response:

```json
{
  "success": false,
  "message": "Feature limit exceeded.",
  "code": "feature_limit_exceeded",
  "errors": {}
}
```

## Provider Abstraction

The billing module uses a provider abstraction, but the simulator adapter is the default runtime implementation.

Current provider readiness and examples are documented in [Payment Provider Integration Readiness](./payment-providers.md).

## Future Dialer Notes

The billing feature-access layer is reusable for future dialer monetization.

Relevant feature keys:

- `dialer.calls.monthly`
- `dialer.concurrent_calls`
- `dialer.recordings.storage_mb`
- `dialer.recordings.retention_days`
- `dialer.webhook_endpoints.count`

## Testing and Validation

See [Billing Testing](./testing.md) for the testing database workflow and targeted validation commands.
