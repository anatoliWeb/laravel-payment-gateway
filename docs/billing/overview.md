# Billing Module Overview

## Purpose

This repository now includes an implemented Billing and Payment Gateway Simulator module.

The module is simulator-safe: it demonstrates a real SaaS billing lifecycle without connecting to Stripe, PayPal, LiqPay, WayForPay, or any other live provider.

## What Exists Now

- payment creation
- payment simulation success/failure
- subscription lifecycle
- invoice lifecycle
- wallet balances and top-ups
- payment methods and payment preferences
- user-facing checkout/payment UI for plan purchase, invoice payment, and wallet top-up
- admin/operator billing management UI for invoices, subscription lookups, audit logs, webhook retry, and permission-gated wallet adjustments
- seller/company ownership-aware billing views with explicit gap notes for missing scoped report endpoints
- idempotency for write operations
- outbound webhook delivery and retry
- scheduler-driven cleanup and expiration jobs
- activity logging for billing events
- provider abstraction with simulator as the default runtime adapter

## Design Goals

- API-first and JSON-first
- service-layer orchestration
- thin controllers
- centralized response and error handling
- queue-based side effects
- explicit ownership checks
- replay-safe writes through idempotency keys
- safe logging with no raw payment secrets

## Runtime Shape

The current implemented API lives under `/api/v1/billing` and uses:

- `auth:sanctum`
- FormRequests for validation
- DTOs for service input
- activity logs for audit visibility
- queue jobs for webhook delivery
- scheduler commands for cleanup and expiration

The exact route map and request examples are documented in [Billing API](./api.md).

## Core Flows

### Payment Lifecycle

Payments can be created for a subscription, plan purchase, invoice, or standalone amount. Wallet top-ups use a dedicated endpoint.

Simulator payments move through `pending`, `processing`, `succeeded`, `failed`, `expired`, or `cancelled` states. Final states are immutable.

### Subscription Lifecycle

Subscriptions are created, activated, cancelled, changed, and renewed through the payment lifecycle and scheduler jobs.

The subscription runtime flow is documented in [Subscription Lifecycle](./subscription-lifecycle.md).

### Invoice Lifecycle

Invoices track billed items, due amount, payment state, and ownership scope. They can be issued, voided, and paid through the API.

The invoice runtime flow is documented in [Invoices](./invoices.md).

### Wallet Lifecycle

Wallets provide internal balances for top-ups, payment fallback, and future automatic billing decisions.

Wallet behavior is documented in [User Wallet Balance](./wallets.md) and [Wallet/Card Payment API Interface](./payment-api.md).

### Webhooks and Queue Processing

Outbound billing webhooks are delivered asynchronously and retried through queue jobs and scheduler commands.

Webhook behavior is documented in [Webhook Delivery Flow](./webhooks.md) and [Cron / Scheduler](./scheduler.md).

## Billing Scope

The module is intentionally broader than chat billing:

- chat usage limits are enforced by billing features
- future dialer limits reuse the same feature-access layer
- company and seller ownership can be attached to payments and invoices
- company and seller UI routes exist as frontend shells even though scoped report APIs are still missing
- manual wallet adjustments remain permission-gated
- admin/operator views stay conservative when list/detail endpoints are missing and show explicit gap notes instead of synthetic data

Future dialer feature keys are documented in [Future Dialer Billing Extension](./future-dialer.md).

## Provider Abstraction

The default provider is the simulator adapter.

Provider readiness, encrypted credentials, customer-scoped provider accounts, and future adapter notes are documented in [Payment Provider Integration Readiness](./payment-providers.md).

## Non-Goals

- no real payment provider integration
- no raw card storage
- no broad rewrite of unrelated app modules
- no repository-pattern cargo culting where a service layer is already enough
- no claims that this is a production gateway for external money movement

## Documentation Map

- [Billing API](./api.md)
- [Billing API Errors](./api-errors.md)
- [Billing Testing](./testing.md)
- [Billing Seller / Company UI](./seller-company-ui.md)
- [Billing Demo Flows](./demo-flows.md)
- [Plans and Feature Access](./plans.md)
- [Idempotency](./idempotency.md)
- [Webhooks](./webhooks.md)
- [Scheduler](./scheduler.md)
- [Payment Providers](./payment-providers.md)

## Status

This billing module is implemented as a simulator-first portfolio system.
The remaining work is refinement, documentation polish, and future provider expansion.
