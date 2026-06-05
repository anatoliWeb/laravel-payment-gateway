# Billing Enums & Statuses Planning

## Purpose

Define stable enum/status vocabulary and transition rules for Billing + Payments modules before implementation.

## Design Principles

- Enum values are stable contract values for DB/API/tests/docs.
- Use lowercase snake_case strings.
- Keep transitions explicit and auditable.
- Prefer immutable final states for payment attempts.
- Keep status semantics reusable across chat billing and future dialer billing.

## Enum Storage Strategy

- Later implementation should use PHP backed enums.
- DB stores string values.
- Avoid MySQL ENUM for flexibility.
- API returns stable string values, never PHP enum class names.
- Validation should reference allowed enum values.

## PlanType

Values:
- `free`
- `paid`
- `enterprise`
- `demo`

Usage:
- `plans.type`
- plan filtering/querying
- seeders/docs/admin plan management

## SubscriptionStatus

Values:
- `pending`
- `trialing`
- `active`
- `past_due`
- `cancelled`
- `expired`
- `suspended`

Meaning:
- `pending`: created, awaiting payment/activation.
- `trialing`: temporary pre-paid access window.
- `active`: full access granted.
- `past_due`: renewal/payment issue state.
- `cancelled`: cancellation requested/applied.
- `expired`: no active access period left.
- `suspended`: admin/system restricted access.

Access guidance:
- access granted: `active`, `trialing`, optionally grace in `past_due` (policy).
- terminal-like: `expired` (and often `cancelled` after period end).
- restorable: `past_due`, `suspended`; `cancelled` by policy.

## PaymentStatus

Values:
- `pending`
- `processing`
- `succeeded`
- `failed`
- `expired`
- `cancelled`
- `refunded` (future optional)

State classes:
- in-progress: `pending`, `processing`
- final-ish: `succeeded`, `failed`, `expired`, `cancelled`, `refunded`

Rules:
- retries create new payment attempts, not `failed -> pending` mutation.
- simulation acts only on legal non-final states.

## PaymentTransactionType

Values:
- `payment_created`
- `payment_processing`
- `payment_succeeded`
- `payment_failed`
- `payment_expired`
- `payment_cancelled`
- `payment_refunded`
- `payment_retry_created`
- `webhook_queued`
- `webhook_delivered`
- `webhook_failed`
- `subscription_activated`
- `subscription_cancelled`
- `invoice_created`
- `invoice_paid`
- `invoice_failed`
- `usage_limit_exceeded`

Notes:
- append-only timeline vocabulary
- supports audit/history APIs
- does not imply all values are MVP-required on day one

## WebhookDeliveryStatus

Values:
- `pending`
- `queued`
- `processing`
- `delivered`
- `failed`
- `permanently_failed`
- `cancelled`

Meaning:
- `pending`: delivery record created
- `queued`: async job dispatched
- `processing`: delivery attempt in progress
- `delivered`: successful callback
- `failed`: retryable failure
- `permanently_failed`: retry budget exhausted
- `cancelled`: manual/system cancellation

Retry policy:
- allowed: `failed`
- optionally allowed by admin policy: `permanently_failed`

## BillingFeature

Decision:
- plan for enum-like core catalog + future extension mechanism.
- core values must align with `plan_features.feature_key`.

Core chat keys:
- `chat.messages.daily`
- `chat.messages.monthly`
- `chat.conversations.active`
- `chat.webhook_endpoints.count`
- `chat.webhook_deliveries.monthly`
- `chat.attachments.monthly`
- `chat.attachments.storage_mb`
- `chat.history_retention_days`
- `chat.external_api.enabled`
- `chat.realtime.enabled`
- `chat.advanced_search.enabled`
- `chat.admin_reply.enabled`

Core future dialer keys:
- `dialer.calls.monthly`
- `dialer.concurrent_calls`
- `dialer.sip_accounts`
- `dialer.recordings.storage_mb`
- `dialer.recordings.retention_days`
- `dialer.webhook_endpoints.count`
- `dialer.webhook_deliveries.monthly`
- `dialer.analytics.enabled`
- `dialer.call_recording.enabled`

Core platform keys:
- `platform.api_tokens.count`
- `platform.activity_logs.retention_days`
- `platform.rate_limit.multiplier`
- `platform.monitoring.enabled`

## UsagePeriod

Values:
- `none`
- `daily`
- `monthly`
- `yearly`
- `billing_cycle`
- `rolling_30_days`
- `lifetime`

Guidance:
- MVP focus: `none`, `daily`, `monthly`, `billing_cycle`
- `rolling_30_days` can be introduced later

## Additional Planned Enums

- `BillingInterval`: `none`, `monthly`, `yearly`, `custom`
- `FeatureValueType`: `boolean`, `integer`, `decimal`, `string`, `json`
- `ResetPolicy`: `none`, `calendar_day`, `calendar_month`, `calendar_year`, `subscription_cycle`, `manual`
- `SubscriptionCancellationMode`: `immediate`, `period_end`
- `PaymentMethod`: `fake_card`, `fake_manual_invoice`, `fake_bank_transfer`, `fake_wallet`, `fake_balance`
- `PaymentProvider`: `simulator`
- `PaymentFailureReason`: `insufficient_funds`, `card_declined`, `expired_card`, `provider_timeout`, `fraud_suspected`, `manual_rejection`, `unknown`
- `IdempotencyStatus`: `processing`, `completed`, `failed`, `expired`
- `WebhookEventType`: `payment.created`, `payment.succeeded`, `payment.failed`, `payment.expired`, `subscription.activated`, `subscription.cancelled`, `invoice.paid`, `usage.limit_exceeded`

## Payment Status Transitions

Phase 15 implements simulator-safe success/failure transitions for `pending` and `processing` payments. See [Payment Simulation Flow](./payment-simulation.md).

| From | To | Allowed | Trigger | Notes |
| --- | --- | --- | --- | --- |
| `null` | `pending` | yes | create payment | initial attempt |
| `pending` | `processing` | yes | async/process start | optional transition |
| `pending` | `succeeded` | yes | simulation/provider success | direct settle |
| `pending` | `failed` | yes | simulation/provider failure | safe failure reason |
| `pending` | `expired` | yes | TTL scheduler | timeout |
| `pending` | `cancelled` | yes | cancel flow | manual/system |
| `processing` | `succeeded` | yes | completion | final |
| `processing` | `failed` | yes | failure | final |
| `processing` | `expired` | yes | timeout policy | final |
| `succeeded` | `refunded` | yes | refund flow (future) | optional future |
| `failed` | `pending` | no | retry | create new payment instead |
| `failed` | `succeeded` | no | n/a | final immutability |
| `expired` | `succeeded` | no | n/a | final immutability |
| `cancelled` | `succeeded` | no | n/a | final immutability |
| `succeeded` | `failed` | no | n/a | final immutability |
| `refunded` | `succeeded` | no | n/a | final immutability |

## Subscription Status Transitions

| From | To | Allowed | Trigger | Notes |
| --- | --- | --- | --- | --- |
| `null` | `pending` | yes | paid signup | awaiting payment |
| `null` | `active` | yes | free signup | default free |
| `pending` | `active` | yes | payment success | activation |
| `pending` | `cancelled` | yes | user/admin | pre-activation cancel |
| `pending` | `expired` | yes | timeout policy | stale pending |
| `trialing` | `active` | yes | trial conversion | normal conversion |
| `trialing` | `cancelled` | yes | cancel | stop trial |
| `active` | `past_due` | yes | renewal issue | payment needed |
| `active` | `cancelled` | yes | cancel action | often period_end |
| `active` | `expired` | yes | period end without renewal | access lost |
| `active` | `suspended` | yes | admin/system | override block |
| `past_due` | `active` | yes | payment recovered | restore |
| `past_due` | `cancelled` | yes | cancel | user/admin action |
| `past_due` | `expired` | yes | overdue timeout | lifecycle end |
| `suspended` | `active` | yes | admin restore | access return |
| `suspended` | `cancelled` | yes | admin/user | final path |
| `cancelled` | `expired` | yes | period end | lifecycle close |
| `expired` | `active` | usually no | re-subscribe | create new subscription preferred |

## Webhook Delivery Status Transitions

| From | To | Allowed | Trigger | Notes |
| --- | --- | --- | --- | --- |
| `null` | `pending` | yes | record create | initial state |
| `pending` | `queued` | yes | dispatch job | async schedule |
| `queued` | `processing` | yes | worker run | attempt started |
| `processing` | `delivered` | yes | success response | final |
| `processing` | `failed` | yes | failure response/timeout | retry candidate |
| `failed` | `queued` | yes | retry policy | next attempt |
| `failed` | `permanently_failed` | yes | max attempts | terminal |
| `permanently_failed` | `queued` | policy | manual admin retry | optional |
| `queued` | `cancelled` | yes | admin/system cancel | stop flow |
| `processing` | `cancelled` | yes | admin/system cancel | stop flow |
| `delivered` | `failed` | no | n/a | terminal |
| `cancelled` | `delivered` | no | n/a | terminal |

## Idempotency Status Notes

- `processing`: request in-flight/locked
- `completed`: response persisted and replayable
- `failed`: stable operation failure persisted and replayable
- `expired`: TTL elapsed / cleanup applied

Payload conflicts return `idempotency_key_conflict` without persisting a separate conflict lifecycle row.

Operational note:
- stale `processing` locks must be recoverable via `locked_until` + cleanup policy

## Error Code Alignment

Status/transition handling should align with stable API codes:
- `invalid_payment_state`
- `payment_already_finalized`
- `payment_expired`
- `payment_retry_not_allowed`
- `invalid_subscription_state`
- `subscription_inactive`
- `feature_limit_exceeded`
- `webhook_retry_not_allowed`
- `idempotency_conflict`

## API Response Alignment

- API exposes enum/status values as stable strings.
- Resource layer should not leak internal implementation details.
- Status values must match documented contract in `docs/billing/api.md`.

## Database Alignment

- Status columns stored as strings.
- `plan_features.feature_key` aligns with `BillingFeature`.
- `feature_usages.period` aligns with `UsagePeriod`.
- Migrations later use string columns + indexes; avoid DB ENUM.
- PHP enum casts can be introduced later in models.

## Testing Strategy

Planned unit coverage:
- payment transition matrix
- subscription transition matrix
- webhook transition matrix
- enum value catalog stability
- feature key catalog consistency

Planned feature coverage:
- invalid transitions return stable errors
- retry creates new payment
- subscription status impacts access behavior
- usage period/reset semantics

Phase 7 creates no tests; this section defines target test intent.

## Non-Goals For This Phase

- No PHP enum classes.
- No migration/model/service/controller/route implementation.
- No runtime transition enforcement code.

## Implementation Notes For Next Phases

- Next phase should begin core billing model planning/implementation sequencing.
- Enum values should be finalized before model casts and validation rules are coded.
- Transition matrices should be encoded in dedicated transition services/policies later.

## Status

- Phase 7 is enum/status planning documentation only.
- No PHP enums, migrations, models, services, controllers, or routes have been created yet.
- Next phase: Core Billing Models.
