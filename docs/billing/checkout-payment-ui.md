# Billing Checkout / Payment UI

## Purpose

This document describes the user-facing checkout and payment screens implemented in the Angular dashboard for Phase 22.2.

The UI is demo-safe:

- it uses the existing billing API only
- it does not collect raw card data
- it does not call providers directly
- it generates client-side idempotency keys for write actions

## Routes / Pages

- `/billing/checkout`
- `/billing/checkout/plan/:planSlug`
- `/billing/invoices/:invoiceId/pay`
- `/billing/wallet/top-up`

## API Endpoints Consumed

- `GET /api/v1/billing/wallet`
- `GET /api/v1/billing/payment-methods`
- `GET /api/v1/billing/payment-preferences`
- `GET /api/v1/billing/invoices/{invoice}`
- `POST /api/v1/billing/payments`
- `POST /api/v1/billing/invoices/{invoice}/pay`
- `POST /api/v1/billing/wallet/top-ups`

## Payment Sources

Checkout and invoice payment screens support the backend payment sources that already exist:

- `wallet`
- `payment_method`
- `wallet_first`

The top-up screen intentionally uses saved simulator payment methods only, because that is how the backend top-up endpoint is modeled.

## Plan Checkout

The checkout screen uses a small static plan reference set because the backend does not expose a public plans catalog endpoint for user checkout.

The form sends:

- plan slug
- amount
- currency
- payment source
- payment strategy
- payment method id when needed
- optional company and seller context
- optional callback URL
- safe metadata only

If a plan needs a custom amount, the UI asks for it explicitly instead of inventing backend pricing rules.

## Invoice Payment Flow

The invoice payment page:

- loads the invoice summary first
- shows the due amount and currency
- lets the user choose a payment source
- submits through the invoice payment endpoint
- renders the returned payment status panel

## Wallet Top-Up Flow

The wallet top-up page:

- loads the current wallet balance
- loads the saved simulator payment methods
- asks for amount and currency
- requires a saved payment method
- submits with an idempotency key
- shows the created payment and wallet transaction result

## Idempotency Behavior

Every create action generates a client-side idempotency key and keeps it stable for that submit attempt.

WHY:
This protects users from duplicate checkout submits caused by double-clicks, browser retries, or network interruptions.

## Stable Error Display

The UI displays the normalized Phase 21 API error envelope:

- stable error code
- human-readable message
- field errors when the backend returns them

No stack traces or raw server errors are shown.

## Simulator Success / Failure Buttons

The user checkout UI does not expose simulator success/failure actions.

Reason:

- the payment simulation routes are bound to the internal payment id
- the current create responses return a public payment UUID, not a create-time payment id

That control remains a better fit for the admin/operator phase.

## What Is Intentionally Not Implemented

- real provider integrations
- raw card collection
- admin/operator billing management
- seller/company billing dashboards
- backend plans catalog UI
- hidden payment simulation controls in the user portal

## Relation to Phase 22.1

Phase 22.1 provides the user billing portal overview:

- wallet
- invoices
- payment methods
- payment preferences
- placeholder views for missing user-scoped endpoints

Phase 22.2 adds the transactional screens that perform writes.

## Relation to Phase 22.3

Phase 22.3 is the correct place for:

- operator billing lists
- payment simulation controls
- webhook retry actions
- manual wallet adjustments
- billing restrictions and overrides

This phase intentionally stays user-facing and lightweight.
