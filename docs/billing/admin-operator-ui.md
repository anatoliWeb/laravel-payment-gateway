# Admin / Operator Billing Management UI

## Purpose

This document describes the Angular admin/operator billing surface for `/admin/billing`.

The UI is designed for operators and admins to inspect billing data, review audit trails, retry safe webhooks, and perform permission-gated wallet adjustments without exposing raw secrets, raw idempotency keys, or raw card data.

## Route

- `/admin/billing`

The route is backed by a lazy-loaded Angular feature module and uses the existing RBAC runtime context for UX-level hiding and disabled states.

## Sections Implemented

### Dashboard

- summary cards for invoices, payments, activity logs, webhook deliveries, wallets, idempotency, provider accounts, and wallet adjustment readiness
- read-only coverage cards for the admin surfaces already wired into the dashboard

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
- subscription list remains future UI work, so the dashboard keeps a focused lookup pattern for now

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

### Read-Only Admin Surfaces

The dashboard now renders read-only review sections for:

- idempotency records
- provider accounts
- billing restrictions / blacklist
- feature overrides

These sections are backed by real list/detail endpoints and use loading, empty, and error states. CRUD actions stay out of scope for this phase.

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

- subscription list screen
- provider account CRUD
- restrictions / blacklist CRUD
- feature override CRUD
- seller/company-specific views

The read-only review sections for idempotency records, provider accounts, restrictions, and feature overrides are implemented already. The remaining items above are intentionally kept for future safe-management work.

## Relation To Other Billing Phases

- Phase 22.1: user billing portal
- Phase 22.2: user checkout / payment UI
- Phase 22.3: admin / operator billing management UI
- Phase 22.4: seller / company billing views

## Notes

The admin billing UI is intentionally conservative. It prefers read-only review sections and clear gap notes when a safe CRUD endpoint does not yet exist.

## Relation to Demo Flows

The failed payment, webhook delivery history, restriction / blacklist, and feature override entries in [Billing Demo Flows](./demo-flows.md) route back here for the current admin/operator surface and the remaining CRUD gaps.
