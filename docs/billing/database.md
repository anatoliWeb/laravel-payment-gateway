# Billing Database Schema Planning

## Purpose

Define the target database schema for Billing + Payment Simulator domains before implementation.  
This phase captures structure, integrity rules, and migration sequencing only.

## Design Principles

- Keep schema aligned with Phase 1-3 strategy.
- Prefer explicit relational columns for query-critical fields.
- Use JSON only for safe extension metadata.
- Preserve auditability with append-only event history where relevant.
- Protect financial/domain history from accidental destructive deletes.
- Keep model reusable across chat billing and future dialer billing.

## Tables Overview

Planned tables:
- `plans`
- `plan_features`
- `subscriptions`
- `feature_usages`
- `payments`
- `payment_transactions`
- `idempotency_keys`
- `webhook_deliveries`

## plans Table

Planned fields:
- `id`
- `uuid` (or `public_id`)
- `slug`
- `name`
- `description` (nullable)
- `type`
- `price_amount`
- `currency`
- `billing_interval`
- `trial_days` (nullable or default `0`)
- `is_active`
- `is_public`
- `sort_order`
- `metadata` (JSON nullable)
- `created_at`
- `updated_at`

Notes:
- `slug` is stable business key.
- `price_amount` stored in minor units (e.g. cents).
- `currency` is ISO-like 3-letter code.
- Free plan uses `price_amount = 0`.
- Enterprise/demo can use custom/manual pricing policy.
- `metadata` is extension context, not core features storage.

Recommended indexes/constraints:
- unique: `slug`
- unique: `uuid`/`public_id`
- index: `type`
- index: (`is_active`, `is_public`)
- index: `sort_order`

## plan_features Table

Planned fields:
- `id`
- `plan_id`
- `feature_key`
- `value`
- `value_type`
- `period` (nullable or normalized non-null)
- `reset_policy` (nullable)
- `is_enabled`
- `metadata` (JSON nullable)
- `created_at`
- `updated_at`

Notes:
- `feature_key` examples: `chat.messages.daily`, `chat.webhook_endpoints.count`, `dialer.calls.monthly`.
- `value` supports integer/boolean/string/decimal/json payload semantics.
- `value_type` removes ambiguity when interpreting `value`.
- `period` supports `daily`, `monthly`, `subscription_cycle`, `lifetime`, `current`.
- `reset_policy` supports `calendar_day`, `calendar_month`, `subscription_cycle`, `manual`, `none`.

Recommended indexes/constraints:
- FK: `plan_id -> plans.id`
- index: `feature_key`
- index: (`plan_id`, `feature_key`)
- unique (target): (`plan_id`, `feature_key`, `period`)
- note: if nullable `period` causes uniqueness edge cases, normalize to non-null sentinel (`none`).

## subscriptions Table

Planned fields:
- `id`
- `uuid` (or `public_id`)
- `user_id`
- `plan_id`
- `status`
- `started_at` (nullable)
- `current_period_start` (nullable)
- `current_period_end` (nullable)
- `trial_ends_at` (nullable)
- `cancelled_at` (nullable)
- `cancel_at_period_end` (boolean)
- `ended_at` (nullable)
- `metadata` (JSON nullable)
- `created_at`
- `updated_at`

Notes:
- MVP owner is `user_id`.
- Future extension may introduce owner abstraction (`owner_type`/`owner_id`) when required.
- Active/trialing subscription is core access gate.
- `current_period_*` supports renewal and usage reset windows.
- `cancel_at_period_end` supports graceful cancellation.

Statuses:
- `pending`
- `trialing`
- `active`
- `past_due`
- `cancelled`
- `expired`
- `suspended`

Recommended indexes/constraints:
- FK: `user_id -> users.id`
- FK: `plan_id -> plans.id`
- index: (`user_id`, `status`)
- index: `current_period_end`
- index: `cancel_at_period_end`
- active-subscription uniqueness: primarily service-layer guarded; MySQL partial unique constraints are limited.

## feature_usages Table

Planned fields:
- `id`
- `user_id`
- `subscription_id` (nullable)
- `plan_id` (nullable)
- `feature_key`
- `period`
- `period_start`
- `period_end`
- `used`
- `limit_value`
- `reset_at` (nullable)
- `metadata` (JSON nullable)
- `created_at`
- `updated_at`

Notes:
- Module-agnostic usage ledger for chat and future dialer.
- `used` and `limit_value` numeric.
- `reset_at` supports scheduler operations.
- `period_start`/`period_end` provide auditability and deterministic usage windows.

Recommended indexes/constraints:
- FK: `user_id -> users.id`
- FK: `subscription_id -> subscriptions.id` (nullable)
- FK: `plan_id -> plans.id` (nullable)
- index: `feature_key`
- index: `reset_at`
- index: `subscription_id`
- unique: (`user_id`, `feature_key`, `period`, `period_start`, `period_end`)
- implementation note: race protection later via transactions/locking.

## payments Table

Planned fields:
- `id`
- `uuid` (or `public_id`)
- `user_id`
- `subscription_id` (nullable)
- `invoice_id` (nullable / future dependency)
- `parent_payment_id` (nullable for retries)
- `amount`
- `currency`
- `status`
- `payment_method`
- `provider`
- `provider_reference` (nullable)
- `description` (nullable)
- `failure_reason` (nullable)
- `callback_url` (nullable)
- `metadata` (JSON nullable)
- `paid_at` (nullable)
- `failed_at` (nullable)
- `expired_at` (nullable)
- `cancelled_at` (nullable)
- `created_at`
- `updated_at`

Notes:
- `provider` can be simulator in MVP.
- `payment_method` supports fake methods (`fake_card`, `fake_manual_invoice`, etc.).
- `amount` in minor units.
- `parent_payment_id` links retry lineage.
- no raw card data.

Statuses:
- `pending`
- `processing`
- `succeeded`
- `failed`
- `expired`
- `cancelled`
- `refunded` (future optional)

Recommended indexes/constraints:
- unique: `uuid`/`public_id`
- index: (`user_id`, `status`)
- index: `subscription_id`
- index: `parent_payment_id`
- index: (`status`, `created_at`)
- index: `provider_reference` (if provider correlation used)

## payment_transactions Table

Planned fields:
- `id`
- `payment_id`
- `type`
- `status_from` (nullable)
- `status_to` (nullable)
- `amount` (nullable)
- `currency` (nullable)
- `message` (nullable)
- `payload` (JSON nullable)
- `created_at`
- `updated_at`

Notes:
- append-only event timeline
- records status transitions and side effects
- never store secrets/raw payment instrument details

Types:
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

Recommended indexes:
- (`payment_id`, `created_at`)
- `type`
- `status_to`

## idempotency_keys Table

Planned fields:
- `id`
- `key`
- `method`
- `endpoint`
- `request_hash`
- `response_body` (JSON nullable)
- `response_status` (nullable)
- `related_type` (nullable)
- `related_id` (nullable)
- `status`
- `locked_until` (nullable)
- `expires_at` (nullable)
- `created_at`
- `updated_at`

Notes:
- required for create/retry payment writes
- same key + same hash replays stored response
- same key + different hash -> conflict
- `locked_until` supports concurrent request protection
- `expires_at` supports cleanup jobs

Recommended indexes/constraints:
- unique: (`key`, `method`, `endpoint`)
- index: (`related_type`, `related_id`)
- index: `expires_at`
- index: `status`

Security:
- store payload hash, not raw secret-bearing request blobs
- sanitize `response_body`

## webhook_deliveries Table

Planned fields:
- `id`
- `uuid` (or `public_id`)
- `payment_id` (nullable)
- `subscription_id` (nullable)
- `invoice_id` (nullable / future dependency)
- `event`
- `url`
- `status`
- `payload` (JSON)
- `response_status` (nullable)
- `response_body` (nullable/sanitized)
- `attempts`
- `max_attempts`
- `next_retry_at` (nullable)
- `last_attempt_at` (nullable)
- `delivered_at` (nullable)
- `failed_at` (nullable)
- `metadata` (JSON nullable)
- `created_at`
- `updated_at`

Notes:
- tracks async callback delivery lifecycle
- can relate to payment/subscription/invoice
- response body should be truncated/sanitized
- payload must not include secrets

Statuses:
- `pending`
- `queued`
- `processing`
- `delivered`
- `failed`
- `permanently_failed`

Recommended indexes:
- (`status`, `next_retry_at`)
- `event`
- `payment_id`
- `subscription_id`
- `delivered_at`
- `failed_at`

## users and subscriptions relation

- User has many subscriptions.
- MVP aims for one active/trialing subscription at a time (policy enforced at service/domain level).
- Current subscription resolution belongs to service layer.
- Team/company ownership is future enhancement, not MVP requirement.

## subscriptions and payments relation

- Subscription has many payments.
- Payment belongs to subscription (nullable for non-subscription billing actions).
- Future invoice linkage can coexist.
- Successful payment drives subscription activation/renewal/plan change.

## payments and transactions relation

- Payment has many payment transactions.
- Payment transaction belongs to one payment.
- History is append-only and ordered by `created_at`.

## payments and webhook deliveries relation

- Payment has many webhook deliveries.
- Webhook delivery may belong to payment (nullable for subscription/invoice-only events).
- Payment outcome events (`payment.succeeded`, `payment.failed`, etc.) should link to payment.

## Index Strategy

Indexes should optimize:
- API lookup by public ID/UUID
- user-centric status queries
- subscription/payment drill-down
- transaction history listing
- usage checks (`feature_key`, period windows)
- scheduler scans (`reset_at`, `expires_at`, `status + next_retry_at`)
- webhook retry processing

Core patterns:
- lookup by `uuid/public_id`
- (`user_id`, `status`)
- `subscription_id`
- (`payment_id`, `created_at`)
- `feature_key`
- `reset_at`
- (`status`, `next_retry_at`)
- `expires_at`
- `created_at` timeline lists

## Unique Constraints

Target unique constraints:
- `plans.slug`
- `plans.uuid/public_id`
- `payments.uuid/public_id`
- `webhook_deliveries.uuid/public_id`
- `idempotency_keys (key, method, endpoint)`
- `plan_features (plan_id, feature_key, period)`
- `feature_usages (user_id, feature_key, period, period_start, period_end)`

MySQL nuance:
- nullable columns in unique constraints can allow unexpected duplicates.
- prefer normalized non-null sentinel values (e.g. `period='none'`) where helpful.

## Foreign Keys

Recommended FK strategy:
- `plan_features.plan_id -> plans.id`: prefer `cascade` for feature rows tied to plan lifecycle.
- `subscriptions.user_id -> users.id`: prefer `restrict` in production-minded design to protect history.
- `subscriptions.plan_id -> plans.id`: prefer `restrict` to avoid orphan commercial history.
- `feature_usages.user_id -> users.id`: prefer `restrict` or archive-first policy.
- `feature_usages.subscription_id -> subscriptions.id`: nullable, prefer `set null` on parent cleanup.
- `feature_usages.plan_id -> plans.id`: nullable, prefer `set null` or `restrict` by archive policy.
- `payments.user_id -> users.id`: prefer `restrict` to preserve financial history.
- `payments.subscription_id -> subscriptions.id`: nullable; prefer `set null` or `restrict`.
- `payments.parent_payment_id -> payments.id`: nullable `set null`.
- `payment_transactions.payment_id -> payments.id`: `cascade` acceptable for strictly dependent append records in non-production cleanup scenarios.
- `webhook_deliveries.payment_id -> payments.id`: nullable `set null` (or `restrict` by retention policy).

Guiding rule:
- avoid accidental deletion of financial/audit history.
- use `restrict`/`set null` where history preservation matters.

## JSON Fields

Planned JSON fields:
- `plans.metadata`
- `plan_features.metadata`
- `subscriptions.metadata`
- `feature_usages.metadata`
- `payments.metadata`
- `payment_transactions.payload`
- `idempotency_keys.response_body`
- `webhook_deliveries.payload`
- `webhook_deliveries.metadata`

Rules:
- no secrets/tokens/passwords
- no raw card data
- safe context only
- sanitize/truncate response artifacts
- keep queryable core fields as explicit columns

## Enum / Status Columns

Recommended approach:
- use string columns + PHP enums/casts in application layer
- avoid rigid MySQL `ENUM` for easier evolution/testing

Planned enum domains:
- `PlanType`
- `BillingInterval`
- `SubscriptionStatus`
- `PaymentStatus`
- `PaymentMethod`
- `PaymentTransactionType`
- `IdempotencyStatus`
- `WebhookDeliveryStatus`
- `FeatureValueType`
- `UsagePeriod`
- `ResetPolicy`

## Money / Amount Storage

- Store money in minor units (`price_amount`, `amount`) as integers.
- Keep currency as ISO-like 3-letter code.
- Avoid floating-point arithmetic in persisted billing values.

## UUID / Public ID Strategy

- Use public identifiers in external API paths/responses.
- Keep internal numeric `id` for FK/index efficiency.
- Apply unique constraints to UUID/public ID fields.

## Auditability Rules

- payment and webhook transitions must be historically traceable.
- payment transaction history remains append-only.
- usage windows (`period_start`/`period_end`) retained for explainability.
- idempotency records preserve deterministic replay/conflict outcomes.

## Data Retention Notes

- retention policies should be scheduler-enforced.
- candidates:
  - webhook delivery history cleanup
  - idempotency key expiration cleanup
  - activity log retention alignment
- archive strategy can be introduced later for long-term analytics/compliance.

## Migration Order

Planned order:
1. `plans`
2. `plan_features`
3. `subscriptions`
4. `feature_usages`
5. `payments`
6. `payment_transactions`
7. `idempotency_keys`
8. `webhook_deliveries`

Dependency notes:
- `subscriptions` depends on `users` and `plans`.
- `payments` depends on `users` and optionally `subscriptions`.
- `payment_transactions` depends on `payments`.
- `webhook_deliveries` depends on `payments`/`subscriptions` optional links.
- `idempotency_keys` is mostly independent but should exist before payment write features are exposed.

## Non-Goals For This Phase

- No migration files in this phase.
- No Eloquent models/services/controllers/routes in this phase.
- No runtime billing/payment behavior implementation.
- No provider integration work.

## Implementation Notes For Next Phases

- Next phase should define Billing domain structure in code organization terms.
- After that, create migrations in safe dependency order with explicit indexes and constraints.
- Service-layer transaction boundaries and idempotency locking should be implemented together with write flows.

Domain structure details: [Billing Domain Architecture](./architecture.md).
API contract planning details: [Billing API Contract](./api.md).
Enum/status planning details: [Enums & Statuses Planning](./statuses.md).

## Status

- Phase 4 is database planning/documentation only.
- No migrations, models, services, controllers, or routes have been created yet.
- Next phase: Billing Domain Structure.
