# Admin / Operator Billing Management UI

## Purpose

This document describes the Angular admin/operator billing surface for `/admin/billing`.

The UI is designed for operators and admins to inspect billing data, review audit trails, retry safe webhooks, and perform permission-gated wallet adjustments without exposing raw secrets, raw idempotency keys, or raw card data.

## Route

- `/admin/billing`

The route is backed by a lazy-loaded Angular feature module and uses the existing RBAC runtime context for UX-level hiding and disabled states.

## Sections Implemented

### Dashboard

- summary cards for invoices, payments, activity logs, webhook deliveries, and wallet adjustment readiness
- visible gap cards for backend areas that do not yet have admin UI screens

### Invoices

- invoice list from `GET /api/v1/billing/invoices`
- invoice detail lookup from `GET /api/v1/billing/invoices/{invoice}`
- pagination support through the existing list response meta

### Payments

- payment list from `GET /api/v1/billing/admin/payments`
- payment detail lookup from `GET /api/v1/billing/admin/payments/{payment}`
- payment transaction history from `GET /api/v1/billing/admin/payments/{payment}/transactions`
- UUID and legacy id binding both work for payment lookup and simulator actions

### Subscriptions

- subscription detail lookup from `GET /api/v1/billing/subscriptions/{subscription}`
- subscription list is available on the backend admin API, while the UI still uses a focused lookup pattern

### Wallet Adjustments

- permission-gated credit/debit form for `POST /api/v1/billing/wallet-adjustments`
- required reason field
- client-generated idempotency key
- no negative amount input
- no raw idempotency key display

### Activity Logs

- generic activity feed from `GET /api/v1/activity`
- filters for search, action, user, model, subject type, and date range
- safe metadata display only

### Webhook Deliveries

- delivery lookup per payment id via `GET /api/v1/billing/payments/{payment}/webhooks`
- retry action via `POST /api/v1/billing/webhooks/{webhookDelivery}/retry`
- retry button is permission-aware and disabled for final or delivered states

### Gap Notes

The UI intentionally shows placeholder cards for:

- idempotency records
- provider accounts
- billing restrictions / blacklist
- feature overrides

These are documented gaps, not missed wiring.

## APIs Consumed

- `GET /api/v1/billing/invoices`
- `GET /api/v1/billing/invoices/{invoice}`
- `GET /api/v1/billing/admin/payments`
- `GET /api/v1/billing/admin/payments/{payment}`
- `GET /api/v1/billing/admin/payments/{payment}/transactions`
- `GET /api/v1/billing/subscriptions/{subscription}`
- `GET /api/v1/activity`
- `GET /api/v1/billing/payments/{payment}/webhooks`
- `POST /api/v1/billing/webhooks/{webhookDelivery}/retry`
- `POST /api/v1/billing/wallet-adjustments`

## Permission Model

Frontend permission checks are UX-only.

The UI hides or disables actions unless the runtime context exposes:

- `billing.wallets.adjust`
- `billing.wallets.credit`
- `billing.wallets.debit`
- `billing.webhooks.retry`
- read permissions for billing data

Backend permissions remain the source of truth. The UI still expects `403` responses when the user is not allowed to act.

## Safety Rules

- wallet adjustments require a reason
- raw idempotency keys are never displayed
- provider secrets are never displayed
- raw card data is never displayed
- webhook callback secrets are never displayed

## What Is Intentionally Not Implemented

- idempotency records screen
- provider account CRUD
- restrictions / blacklist CRUD
- feature override CRUD
- seller/company-specific views

Those items depend on backend APIs that are not exposed in this phase.

## Relation To Other Billing Phases

- Phase 22.1: user billing portal
- Phase 22.2: user checkout / payment UI
- Phase 22.3: admin / operator billing management UI
- Phase 22.4: seller / company billing views

## Notes

The admin billing UI is intentionally conservative. It prefers a visible gap note over fake data when the backend does not expose a safe list/detail endpoint.

## Relation to Demo Flows

The failed payment, webhook delivery history, restriction / blacklist, and feature override entries in [Billing Demo Flows](./demo-flows.md) route back here for the current admin/operator surface and documented gaps.
