# Payment Gateway Simulator Design

## Purpose

Define the Phase 3 architecture for an internal Payment Gateway Simulator inside the Billing module.  
The simulator must mimic payment-provider behavior for portfolio-grade backend design without integrating real external providers.

## Design Principles

- Internal simulator, external-provider-like behavior.
- API-first contract with predictable status transitions.
- Idempotent write operations for create/retry flows.
- Append-only transaction history for auditability.
- Async webhook delivery through queue workers.
- Scheduler-driven lifecycle maintenance (expiration/retry/cleanup).
- Safe logging: no secrets, no raw sensitive payment data.
- Reusable across subscriptions, invoices, paid chat, and future dialer billing.

## Non-Goals

- No Stripe/PayPal/LiqPay/WayForPay integration.
- No real card processing or PCI payment handling.
- No direct provider SDK integration in this phase.
- No code implementation (models/migrations/services/controllers/routes).

## Simulator Concept

The simulator is an internal billing component that behaves like an external payment provider from API and state-transition perspective:
- create payment intent/attempt
- move payment through provider-like statuses
- simulate success/failure/expiration/retry
- emit webhook-like callbacks asynchronously
- persist immutable transaction history

This gives realistic architecture patterns while keeping full control in local/demo environments.

## Supported Fake Payment Methods

| Method | Purpose | Default behavior | Instant success | Delayed success | Failure simulation | Expiration | MVP |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `fake_card` | Main default simulation path | Pending -> success/failure by scenario | Yes | Yes | Yes | Yes | Yes |
| `fake_manual_invoice` | Manual backoffice-like confirmation path | Pending until explicit action | No | Yes | Yes | Yes | Yes |
| `fake_bank_transfer` | Delayed settlement scenario | Processing/pending then final | No | Yes | Yes | Yes | Future |
| `fake_wallet` | Alternative consumer method simulation | Similar to card with simpler metadata | Yes | Yes | Yes | Yes | Future |
| `fake_balance` | Internal balance-style payment simulation | Immediate internal settle/fail | Yes | No | Yes | No/optional | Future |

MVP should start with `fake_card` and `fake_manual_invoice`.

## Payment Creation Flow

1. Client selects plan/subscription billing action.
2. Billing context resolves invoice/subscription linkage.
3. Client sends create-payment request with `Idempotency-Key`.
4. System validates ownership and billing context.
5. System runs demo-safe payment risk checks.
6. System creates payment in `pending`, `processing`, or `succeeded` depending on source.
7. System appends `payment_created` transaction event.
8. System stores idempotency record (request hash + response snapshot).
9. System returns payment public identifier + simulator actions.
10. Activity log records `payment.created`.

Design notes:
- Creation happens in DB transaction boundary.
- Idempotency prevents duplicate payment rows.
- Payment uses public UUID/public ID in API responses.
- Risk guard details: [Payment Risk & Fraud Guard](./payment-risk.md).

## Payment Success Simulation Flow

Implemented runtime details are documented in [Payment Simulation Flow](./payment-simulation.md).

1. Authorized caller requests simulate-success.
2. System validates current payment state.
3. System locks payment row / prevents concurrent conflicting transitions.
4. Status changes to `succeeded`.
5. `paid_at` timestamp is set.
6. `payment_succeeded` transaction is appended.
7. Related subscription activates/renews/upgrades (if applicable in future implementation).
8. Related invoice marked paid (if applicable in future implementation).
9. `payment.succeeded` webhook event is queued.
10. Activity log records `payment.succeeded`.

Behavior rules:
- Repeated success call should be idempotent (return current succeeded state) or deterministic safe conflict based on final API policy.
- Finalized `failed/cancelled/expired` payments should not be force-converted to succeeded via same attempt.

## Payment Failure Simulation Flow

Implemented runtime details are documented in [Payment Simulation Flow](./payment-simulation.md).

1. Authorized caller requests simulate-failure.
2. System validates failure reason.
3. System validates current payment state.
4. Status changes to `failed`.
5. `failed_at` + safe `failure_reason` are set.
6. `payment_failed` transaction is appended.
7. Subscription remains pending/past_due/unchanged depending on billing context.
8. Invoice remains payment_pending/failed depending on policy.
9. `payment.failed` webhook event is queued.
10. Activity log records `payment.failed`.

Safe failure reason set:
- `insufficient_funds`
- `card_declined`
- `expired_card`
- `provider_timeout`
- `fraud_suspected`
- `manual_rejection`
- `unknown`

## Payment Expiration Flow

- Pending payments expire after configured TTL.
- Scheduler finds overdue pending attempts and marks them `expired`.
- Transaction history appends `payment_expired`.
- Optional `payment.expired` webhook is queued.
- Expired attempts are immutable final states and cannot be paid directly.
- Retry/new attempt flow should create a fresh payment attempt.

Suggested configurable key:
- `PAYMENT_PENDING_TTL_MINUTES` (e.g. 30 or 60 for demo).

## Payment Retry Flow

- Retry does not mutate failed payment back to pending.
- Retry creates a new payment attempt linked to same invoice/subscription.
- New attempt may carry `parent_payment_id`/`retry_of_payment_id`.
- Retry request requires `Idempotency-Key`.
- Retry appends `payment_retry_created` transaction.
- New attempt can independently succeed/fail/expire.

Reasoning:
- Better auditability.
- Immutable historical final states.

## Webhook Callback Flow

1. Billing event occurs (payment/subscription/invoice/usage).
2. Payload is built.
3. Webhook delivery record is created.
4. Delivery job is pushed to queue.
5. Job sends callback POST.
6. Response code + safe response summary are stored.
7. Delivery marked `delivered` or `failed`.
8. Failed delivery retried with backoff.
9. Max-attempts reached -> `permanently_failed`.
10. Activity log records webhook events.

Planned events:
- `payment.created`
- `payment.succeeded`
- `payment.failed`
- `payment.expired`
- `subscription.activated`
- `subscription.cancelled`
- `invoice.paid`
- `usage.limit_exceeded`

## Idempotency Behavior

- `Idempotency-Key` required for create-payment and retry-payment operations.
- Same key + same payload -> replay same response.
- Same key + different payload -> conflict.
- Store: key, request hash, status, response snapshot, related model reference, expiry.
- Concurrent duplicate requests require locking/transaction-safe behavior.
- Idempotency is for write endpoints, not read/status endpoints.

Proposed API semantics:
- Missing key -> `400` or `422` (final convention to be defined in implementation).
- Conflict -> `409`.
- Replay -> original `200/201` payload with optional replay marker.

## Transaction History Behavior

Planned transaction event types:
- `payment_created`
- `payment_processing`
- `payment_succeeded`
- `payment_failed`
- `payment_expired`
- `payment_cancelled`
- `payment_retry_created`
- `webhook_queued`
- `webhook_delivered`
- `webhook_failed`
- `subscription_activated`
- `invoice_paid`

Tracked fields (target model shape):
- payment reference
- event type
- status transition (`status_from`, `status_to`)
- amount/currency snapshot
- safe message
- structured metadata
- timestamp

Rules:
- Append-only log.
- No sensitive card/provider secrets.
- Exposable via API history endpoints.

## Payment Metadata Structure

Recommended safe metadata keys:
- `source`
- `plan_slug`
- `subscription_public_id`
- `invoice_public_id`
- `user_public_id`
- `billing_action`
- `retry_of_payment_id`
- `simulator_method`
- `simulator_scenario`
- `correlation_id`
- optional safe request context (e.g. hashed IP footprint)

Hard restrictions:
- No raw card data.
- No secrets/tokens/passwords.
- No full webhook secret material.
- No unnecessary personal sensitive data.

## Simulator API Contract

Base prefix: `/api/v1/billing`

| Endpoint | Purpose | Auth | Idempotency | Status impact |
| --- | --- | --- | --- | --- |
| `GET /plans` | List available plans | Yes | No | None |
| `GET /subscription` | Get current subscription | Yes | No | None |
| `POST /subscriptions` | Create/select subscription intent | Yes | Optional | May create pending/active subscription |
| `POST /subscriptions/change-plan` | Request plan change | Yes | Optional | Pending change or immediate change (policy-based) |
| `POST /subscriptions/cancel` | Cancel subscription | Yes | Optional | Cancel now or period-end flag |
| `POST /payments` | Create payment attempt | Yes | Yes | Creates `pending` payment |
| `GET /payments` | List payment attempts | Yes | No | None |
| `GET /payments/{payment}` | Payment details | Yes | No | None |
| `GET /payments/{payment}/status` | Lightweight status | Yes | No | None |
| `GET /payments/{payment}/transactions` | Payment history log | Yes | No | None |
| `POST /payments/{payment}/simulate/success` | Simulate provider success | Yes | Optional | `pending/processing -> succeeded` |
| `POST /payments/{payment}/simulate/failure` | Simulate provider failure | Yes | Optional | `pending/processing -> failed` |
| `POST /payments/{payment}/retry` | Create new retry attempt | Yes | Yes | New `pending` payment attempt |
| `GET /payments/{payment}/webhooks` | List webhook deliveries for payment | Yes | No | None |
| `POST /webhook-deliveries/{webhookDelivery}/retry` | Manual webhook redelivery | Yes | Optional | Delivery status transition |

## Status Model

PaymentStatus:
- `pending`
- `processing`
- `succeeded`
- `failed`
- `expired`
- `cancelled`
- `refunded` (future optional)

WebhookDeliveryStatus:
- `pending`
- `queued`
- `processing`
- `delivered`
- `failed`
- `permanently_failed`

IdempotencyStatus (optional internal state):
- `processing`
- `completed`
- `conflict`
- `expired`

Allowed transition guidance:
- `pending -> processing/succeeded/failed/expired/cancelled`
- `processing -> succeeded/failed/expired`
- terminal states (`succeeded`, `failed`, `expired`, `cancelled`) immutable for same attempt
- webhook: `pending -> queued -> processing -> delivered|failed -> permanently_failed`

## Error Model

Stable error cases:
- `validation_failed`
- `idempotency_key_required`
- `idempotency_conflict`
- `payment_not_found`
- `invalid_payment_state`
- `payment_already_finalized`
- `payment_expired`
- `payment_retry_not_allowed`
- `subscription_not_found`
- `webhook_delivery_not_found`
- `webhook_retry_not_allowed`

Error contract rules:
- Unified API response envelope.
- Stable error codes for client handling.
- No internal stack traces/exception internals in response.

## Queue Responsibilities

- Dispatch webhook sender jobs.
- Process async payment side-effects where needed.
- Dispatch billing notification jobs where applicable.
- Retry webhook deliveries with backoff policy.
- Keep callback I/O out of synchronous request path.

Planned queue channels:
- `billing`
- `webhooks`
- `notifications`
- `default` (fallback)

Retry/backoff direction:
- bounded retry attempts
- exponential or step backoff
- permanent-failure final state

## Scheduler Responsibilities

Planned simulator-related scheduled tasks:
- expire pending payments
- retry failed webhook deliveries
- cleanup stale idempotency records
- cleanup old webhook deliveries
- detect stuck processing payments
- future recurring invoice/payment generation

Implemented/foundation command names:
- `billing:expire-pending-payments`
- `billing:retry-webhooks`
- `billing:reset-usage`
- `billing:check-subscription-expiration`
- `billing:cleanup`

## Activity Logging Strategy

Planned ActivityLog events:
- `payment.created`
- `payment.succeeded`
- `payment.failed`
- `payment.expired`
- `payment.retry_created`
- `idempotency.replayed`
- `idempotency.conflict`
- `webhook.queued`
- `webhook.delivered`
- `webhook.failed`
- `subscription.activated_by_payment`
- `invoice.paid_by_payment`

Safe properties:
- public IDs, slugs, statuses, reason codes, correlation IDs
- avoid secrets/sensitive raw payloads

## Security / Safety Notes

- Never store raw card data.
- Never log secrets/tokens/passwords.
- Keep webhook secret material masked/hashed where applicable.
- Validate all simulator actions with ownership/authorization checks.
- Protect state transitions from races via locking/transactions.
- Keep internal/provider error mapping deterministic and non-leaky.

## Testing Strategy

Planned feature tests:
- create payment requires idempotency key
- create payment succeeds
- duplicate idempotency replays same response
- same key with different payload returns conflict
- simulate success
- simulate failure
- expired payment cannot succeed
- retry creates new payment attempt
- webhook job dispatched
- transaction history appended

Planned unit tests:
- DTO mapping
- status transition rules
- idempotency hashing behavior
- webhook payload builder
- metadata sanitizer

Phase 3 creates no tests; this section defines target coverage only.

## Implementation Notes For Next Phases

- Phase 4 should formalize schema for payments, payment transactions, idempotency keys, and webhook deliveries.
- Phase 5+ should implement service-layer transitions and contracts using DTO/FormRequest patterns.
- Queue/scheduler commands should be idempotent and replay-safe from first implementation.

Database planning details: [Billing Database Schema Planning](./database.md).
Domain structure details: [Billing Domain Architecture](./architecture.md).
API reference details: [Billing API](./api.md).
Enum/status planning details: [Enums & Statuses Planning](./statuses.md).

## Status

- Phase 3 is design/documentation only.
- No payment models, migrations, services, controllers, or routes have been created yet.
- Next phase: Database Schema Planning.
