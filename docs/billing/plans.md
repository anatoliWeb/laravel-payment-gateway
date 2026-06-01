# Billing Plans & Feature Access Design

## Purpose

Define the Phase 2 design for plans, feature access, subscriptions, and usage tracking on top of the existing SaaS foundation.  
This is a planning document, not implementation code.

## Design Principles

- Reuse existing SaaS architecture (Auth/RBAC/Chat/Notifications/Activity/Queue/Reverb).
- Keep billing module domain-oriented and module-agnostic.
- Prefer stable keys (`slug`, `feature_key`) over hardcoded feature checks.
- Support both chat billing now and dialer billing later.
- Keep MVP simple (one plan per subscription) while leaving extension points.

## Planned Database Tables

- `plans`
- `plan_features`
- `subscriptions`
- `feature_usages`
- `subscription_items` (planned extension, not required for MVP)

## plans Table Design

Planned fields:
- `id`
- `uuid` (or `public_id`)
- `slug`
- `name`
- `description`
- `type`
- `price_amount` (minor units, e.g. cents)
- `currency`
- `billing_interval`
- `trial_days`
- `is_active`
- `is_public`
- `sort_order`
- `metadata` (JSON)
- `created_at`
- `updated_at`

Notes:
- `slug` is the stable integration key for code/tests/seeding.
- Free plan has `price_amount = 0`.
- Enterprise/demo may use custom or nullable pricing strategy.
- `metadata` is for auxiliary settings, not a replacement for normalized feature rows.

Indexes/constraints:
- unique index on `slug`
- index on `is_active`, `is_public`
- index on `type`
- index on `sort_order`

## plan_features Table Design

Planned fields:
- `id`
- `plan_id`
- `feature_key`
- `value`
- `value_type`
- `period`
- `reset_policy`
- `is_enabled`
- `metadata` (JSON)
- `created_at`
- `updated_at`

Notes:
- `feature_key` must be universal (e.g. `chat.messages.daily`, `dialer.calls.monthly`).
- `value` can represent numeric/bool/string/json depending on `value_type`.
- `period` and `reset_policy` are required for usage-based limits.
- `is_enabled` allows safe toggling without deleting records.

Indexes/constraints:
- unique composite where applicable: (`plan_id`, `feature_key`, `period`)
- index on `feature_key`
- index on `plan_id`

## subscriptions Table Design

Planned fields:
- `id`
- `uuid` (or `public_id`)
- `user_id` (MVP owner)
- `plan_id`
- `status`
- `started_at`
- `current_period_start`
- `current_period_end`
- `trial_ends_at`
- `cancelled_at`
- `cancel_at_period_end`
- `ended_at`
- `metadata` (JSON)
- `created_at`
- `updated_at`

Notes:
- MVP ownership is user-based; future evolution can support team/company owner.
- Access decisions rely on active/trialing subscription status + plan features.
- `current_period_*` is needed for renewal and usage resets.
- `cancel_at_period_end` enables graceful cancellation flow.

Planned statuses:
- `pending`
- `trialing`
- `active`
- `past_due`
- `cancelled`
- `expired`
- `suspended`

## subscription_items Decision

MVP decision: **optional / not required in first implementation**.

Reason:
- MVP uses one plan per subscription.
- Add-ons and seat/rated pricing can be introduced later without blocking MVP.

Future `subscription_items` design (planned):
- `id`
- `subscription_id`
- `feature_key` (or `item_type`)
- `quantity`
- `unit_price_amount`
- `currency`
- `metadata`
- `created_at`
- `updated_at`

Future use cases:
- extra seats
- extra storage
- add-on dialer minutes
- add-on webhook capacity
- premium support

## feature_usages Table Design

Planned fields:
- `id`
- `user_id` (or future `owner_id`)
- `subscription_id` (nullable)
- `plan_id` (nullable)
- `feature_key`
- `period`
- `period_start`
- `period_end`
- `used`
- `limit`
- `reset_at`
- `metadata` (JSON)
- `created_at`
- `updated_at`

Notes:
- Central usage ledger for limits across chat and future dialer.
- Module-agnostic by design.
- `used`/`limit` should be numeric.
- `period_start`/`period_end` improve auditability.
- `reset_at` drives scheduler resets.

Indexes/constraints:
- unique composite on owner + feature window (e.g. `user_id`, `feature_key`, `period_start`, `period_end`)
- index on `feature_key`
- index on `reset_at`
- index on `subscription_id`

## Plan Slugs

Canonical slugs:
- `free`
- `basic`
- `pro`
- `enterprise` (or `business`)
- `demo_enterprise` (optional demo seed plan)

`slug` is stable and should be reused in seeders, tests, and API payload contracts.

## Default Free Plan

Purpose:
- onboarding/demo default
- safe baseline for all new users
- enough access to evaluate product value

Demo default limits (subject to tuning):
- `chat.messages.daily`: 50
- `chat.messages.monthly`: 500
- `chat.conversations.active`: 3
- `chat.webhook_endpoints.count`: 0 or 1
- `chat.webhook_deliveries.monthly`: 0 or 100
- `chat.attachments.monthly`: 10
- `chat.history_retention_days`: 7
- `chat.external_api.enabled`: false
- `chat.realtime.enabled`: true
- `dialer.calls.monthly`: 0
- `dialer.sip_accounts`: 0

## Basic Plan

Purpose:
- entry paid tier
- meaningful increase for small teams
- basic webhook-enabled workflows

Demo default limits:
- `chat.messages.daily`: 500
- `chat.messages.monthly`: 10000
- `chat.conversations.active`: 25
- `chat.webhook_endpoints.count`: 3
- `chat.webhook_deliveries.monthly`: 5000
- `chat.attachments.monthly`: 250
- `chat.history_retention_days`: 30
- `chat.external_api.enabled`: true
- `chat.realtime.enabled`: true
- `dialer.calls.monthly`: 0 or 100 (future demo)
- `dialer.sip_accounts`: 0 or 1 (future demo)

## Pro Plan

Purpose:
- production-like usage
- advanced chat + API + webhook operations
- starter dialer-related limits for future extension

Demo default limits:
- `chat.messages.daily`: 5000
- `chat.messages.monthly`: 100000
- `chat.conversations.active`: 250
- `chat.webhook_endpoints.count`: 10
- `chat.webhook_deliveries.monthly`: 50000
- `chat.attachments.monthly`: 2500
- `chat.history_retention_days`: 90
- `chat.external_api.enabled`: true
- `chat.realtime.enabled`: true
- `chat.advanced_search.enabled`: true
- `dialer.calls.monthly`: 1000
- `dialer.concurrent_calls`: 2
- `dialer.sip_accounts`: 2
- `dialer.recordings.storage_mb`: 1024

## Enterprise / Demo Plan

Purpose:
- high-limit portfolio/demo tier
- future enterprise customization point
- advanced ops/webhook/logging profile

Typical behavior:
- high limits
- longer retention
- priority delivery behavior
- advanced audit/monitoring scopes
- future dialer features enabled
- custom/manual pricing strategy

Notes:
- Enterprise can be custom-priced.
- `demo_enterprise` can be pre-seeded for portfolio demonstrations.

## Chat Feature Limits

| Feature key | Meaning | Value type | Period | Example plans |
| --- | --- | --- | --- | --- |
| `chat.messages.daily` | Messages allowed per day | integer | daily | Free/Basic/Pro/Enterprise |
| `chat.messages.monthly` | Messages allowed per month | integer | monthly | Free/Basic/Pro/Enterprise |
| `chat.conversations.active` | Active conversation cap | integer | current_state | Free/Basic/Pro/Enterprise |
| `chat.webhook_endpoints.count` | Max webhook endpoints | integer | current_state | Basic+ |
| `chat.webhook_deliveries.monthly` | Monthly webhook delivery quota | integer | monthly | Basic+ |
| `chat.attachments.monthly` | Monthly attachment operations/quota | integer | monthly | Free+ |
| `chat.history_retention_days` | History retention window | integer | policy | Free+ |
| `chat.external_api.enabled` | External API availability | boolean | policy | Basic+ |
| `chat.realtime.enabled` | Realtime usage availability | boolean | policy | Free+ |
| `chat.advanced_search.enabled` | Advanced search features | boolean | policy | Pro+ |
| `chat.admin_reply.enabled` | Admin reply/support workflow feature | boolean | policy | Plan-dependent |

## Future Dialer Feature Limits

| Feature key | Meaning | Value type | Period | Notes |
| --- | --- | --- | --- | --- |
| `dialer.calls.monthly` | Outbound/inbound call quota | integer | monthly | Future module |
| `dialer.concurrent_calls` | Concurrent call channels | integer | current_state | Future module |
| `dialer.sip_accounts` | SIP account capacity | integer | current_state | Future module |
| `dialer.recordings.storage_mb` | Recording storage quota | integer | monthly/cycle | Future module |
| `dialer.recordings.retention_days` | Recording retention | integer | policy | Future module |
| `dialer.webhook_endpoints.count` | Dialer webhook endpoints | integer | current_state | Future module |
| `dialer.webhook_deliveries.monthly` | Dialer webhook quota | integer | monthly | Future module |
| `dialer.analytics.enabled` | Dialer analytics feature flag | boolean | policy | Future module |
| `dialer.call_recording.enabled` | Call recording feature flag | boolean | policy | Future module |

Phase 2 defines keys only; implementation belongs to future dialer scope.

## Storage & Retention Limits

Planned keys:
- `chat.history_retention_days`
- `chat.attachments.storage_mb` (optional)
- `activity_logs.retention_days`
- `webhook_deliveries.retention_days`
- `dialer.recordings.storage_mb`
- `dialer.recordings.retention_days`

Notes:
- Retention enforcement belongs to scheduler/cleanup jobs.
- Storage can be tracked in `feature_usages` or dedicated accounting later.

## Plan Upgrade Rules

- Upgrade can apply immediately after successful payment.
- New plan features become active immediately on success.
- If payment is pending, state may remain pending change.
- Proration/credit is optional and out of MVP scope.
- Log upgrade request + activation in activity log.
- Emit webhook/event after successful upgrade application.

## Plan Downgrade Rules

- Downgrade generally applies at next billing period.
- Avoid immediate destructive access removal.
- If usage exceeds target plan limits, block new billable actions but preserve existing data access.
- Store pending downgrade intent in subscription metadata/planned change.
- Scheduler applies downgrade at period end.

## Subscription Cancellation Rules

- Support immediate cancel and cancel-at-period-end.
- MVP default for paid plans: `cancel_at_period_end=true`.
- Free plan fallback can be immediate.
- Cancelled paid subscription eventually transitions to expired.
- Log cancellation actions in activity log.
- Emit `subscription.cancelled` webhook/event.
- After period end, user falls back to Free plan baseline.

## Feature Access Resolution

Planned decision order for future `FeatureAccessService`:
1. Resolve active/trialing subscription for owner.
2. Resolve feature policy from current plan features.
3. Resolve usage row for current period.
4. Apply hard/soft limit behavior policy.
5. Return allow/deny decision + reason + current usage + limit.

Phase 2 defines only the contract logic, not implementation.

## Usage Tracking Rules

- Increment usage when billable action completes.
- Use idempotent increment keys to avoid double-count on retries.
- Apply compensation/decrement strategy when operation fails after provisional increment.
- Reset counters by scheduler using period/reset policy.
- Protect from race conditions in implementation via transaction/locking strategy.

## Activity Logging Rules

Planned billing activity events:
- `subscription.created`
- `subscription.upgraded`
- `subscription.downgraded`
- `subscription.cancelled`
- `feature.limit_exceeded`
- `usage.reset`
- `feature.access_denied`

## Non-Goals For This Phase

- No Laravel migrations in this phase.
- No models/services/controllers in this phase.
- No billing/payment runtime logic in this phase.
- No route/API implementation in this phase.

## Implementation Notes For Next Phases

Next phase focus:
- Payment Gateway Simulator design and payment lifecycle contract.
- Then schema implementation phase for plans/features/subscriptions/usages.
- Then service-layer implementation with DTO/FormRequest + tests.

Schema-level planning details: [Billing Database Schema Planning](./database.md).

## Status

- Phase 2 is design/documentation only.
- No migrations/models/services have been created yet.
- Next phase: Payment Gateway Simulator Design.
