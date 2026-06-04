# Billing API Contract

## Purpose

Define the Phase 6 API contract for Billing + Payments modules before implementation.  
This document describes endpoint behavior, auth/RBAC, idempotency, envelopes, and async expectations.

## Design Principles

- Base prefix: `/api/v1/billing`
- API-first, JSON-only
- Reuse existing auth/Sanctum/RBAC patterns
- Reuse unified API response envelope
- Stable error codes
- Prefer public IDs/UUIDs over raw DB IDs where practical
- FormRequest/Resource/Service are planned implementation patterns (not created in this phase)
- No secrets/raw provider internals in response/logs

## Base URL

- Prefix: `/api/v1/billing`

## Authentication

- Most endpoints require authenticated user context.
- `GET /plans`: recommended public read-only for safe demo catalog (can be switched to auth-required mode by policy).
- Payment create/retry endpoints require authentication.
- Simulation endpoints require elevated permission.
- Current subscription/usage/payments are user-scoped.

## Authorization / RBAC

Planned permissions:
- `billing.plans.view`
- `billing.subscriptions.view`
- `billing.subscriptions.manage`
- `billing.usage.view`
- `billing.payments.view`
- `billing.payments.create`
- `billing.payments.simulate`
- `billing.payments.retry`
- `billing.webhooks.view`
- `billing.webhooks.retry`

Access rules:
- Regular users: own billing data only.
- Admin/system roles: broader visibility/operations.
- Simulator endpoints are not public.

## Common Headers

JSON:
- `Accept: application/json`
- `Content-Type: application/json` (writes)

Auth/tracing/idempotency:
- `Authorization: Bearer ...` or session auth (per existing app convention)
- `Idempotency-Key` (required for selected write endpoints)
- `X-Request-Id` (optional tracing)

## Idempotency Rules

- Required:
  - `POST /payments`
  - `POST /payments/{payment}/retry`
- Not required for `GET`.
- Recommended not required for simulation endpoints (state-machine + permission guarded admin/demo actions).
- Same key + same payload: replay same response.
- Same key + different payload: `409 idempotency_conflict`.
- Missing key on required service operations: stable error (`idempotency_key_required`).
- Idempotency records expire by configurable TTL.

## Common Response Envelope

```json
{
  "success": true,
  "message": "Payment created successfully.",
  "data": {},
  "meta": {}
}
```

## Common Error Envelope

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {},
  "code": "validation_failed"
}
```

## Error Codes

- `validation_failed`
- `unauthenticated`
- `forbidden`
- `not_found`
- `plan_not_found`
- `plan_not_available`
- `subscription_not_found`
- `subscription_inactive`
- `invalid_plan_change`
- `feature_not_available`
- `feature_limit_exceeded`
- `payment_not_found`
- `payment_already_finalized`
- `invalid_payment_state`
- `payment_expired`
- `idempotency_key_required`
- `idempotency_conflict`
- `webhook_delivery_not_found`
- `webhook_retry_not_allowed`

## Pagination / Filtering / Sorting

- List endpoints use existing app pagination style.
- Support `page` / `per_page` for list/timeline endpoints.
- Filters must be whitelisted and documented.
- Sorting limited to safe fields (`created_at`, `status`, `amount` where applicable).

## Plans Endpoints

### `GET /api/v1/billing/plans`

- Purpose: list available plans.
- Auth: public read-only (recommended) or authenticated read-only by policy.
- Query: `active`, `public`, `type`, `currency`.
- Response: plans list + feature summary.
- Activity log: usually none for simple reads.

## Current Subscription Endpoint

### `GET /api/v1/billing/current-subscription`

- Purpose: get current user effective subscription.
- Auth: required.
- Response: subscription, plan, current period, cancel-at-period-end, key feature summary.
- Recommendation: if no paid record, return effective free/default shape for frontend simplicity.

## Subscription Management Endpoints

### `POST /api/v1/billing/subscriptions`

- Purpose: create/select subscription for plan.
- Body: `plan_slug`, optional `billing_interval`, `payment_method`, `callback_url`, `metadata`.
- Behavior:
  - free plan: active immediately
  - paid plan: pending subscription + payment required flow
- Idempotency:
  - required if endpoint creates payment attempt
  - optional if pure pending subscription creation mode
- Response: subscription + `payment_required` + optional payment payload.

### `POST /api/v1/billing/subscriptions/change-plan`

- Purpose: request upgrade/downgrade.
- Body: `target_plan_slug`, `apply_mode` (`immediate`/`period_end`), optional payment fields.
- Behavior:
  - upgrade usually immediate after successful payment
  - downgrade usually period-end
- Response: subscription + pending change + optional payment.

### `POST /api/v1/billing/subscriptions/cancel`

- Purpose: cancel subscription.
- Body: `mode` (`immediate`/`period_end`), optional `reason`.
- MVP behavior: paid defaults to period-end cancellation.
- Response: subscription + cancellation state.

## Usage Endpoint

### `GET /api/v1/billing/usage`

- Purpose: fetch user feature usage.
- Query: optional `feature_key`, `period`, `module` (`chat`/`dialer`/`platform`).
- Response: `used`, `limit`, `remaining`, `reset_at` per item.
- Scope: own usage only unless elevated permission.

## Payments Endpoints

### `GET /api/v1/billing/payments`

- Purpose: list payments.
- Query: `status`, `payment_method`, `date_from`, `date_to`, paging.
- Scope: own data by default; wider scope only with elevated permission.

### `POST /api/v1/billing/payments`

- Purpose: create payment attempt.
- Idempotency: required.
- Body: optional context refs (`plan_slug`, `subscription_public_id`, future `invoice_public_id`), `currency`, `payment_method`, optional `payment_method_uuid`, optional callback/metadata.
- Behavior: validate context, resolve payment source, create payment, append initial transaction.
- Phase 13 supports `payment_source` values:
  - `wallet`: debit internal wallet balance and create a succeeded internal payment
  - `payment_method`: create a simulator-safe payment-method attempt
  - `wallet_first`: debit wallet when funds are available, otherwise fall back to default active payment method
- Phase 14 stores central user-scoped idempotency replay/conflict state before payment side effects.
- Phase 13 does not activate subscriptions, send webhooks, or call real providers.
- Phase 13.1 runs demo-safe payment risk checks before wallet debit or simulator payment-method processing.
- Response:
  - `201` on first create
  - `201` on idempotency replay of the original create result

Payment method and preference persistence details: [Payment Methods & User Payment Preferences](./payment-methods.md).
Payment risk guard details: [Payment Risk & Fraud Guard](./payment-risk.md).
Wallet/card API runtime details: [Wallet/Card Payment API Interface](./payment-api.md).

## Wallet/Card Payment API

Phase 13.3 exposes authenticated wallet and saved payment method APIs:
- `GET /api/v1/billing/wallet`
- `GET /api/v1/billing/wallet/balances`
- `GET /api/v1/billing/wallet/transactions`
- `POST /api/v1/billing/wallet/top-ups`
- `GET /api/v1/billing/payment-methods`
- `POST /api/v1/billing/payment-methods`
- `PATCH /api/v1/billing/payment-methods/{paymentMethod}`
- `DELETE /api/v1/billing/payment-methods/{paymentMethod}`
- `POST /api/v1/billing/payment-methods/{paymentMethod}/set-default`
- `GET /api/v1/billing/payment-preferences`
- `PATCH /api/v1/billing/payment-preferences`

Full endpoint behavior and safety rules are documented in [Wallet/Card Payment API Interface](./payment-api.md).

Provider-neutral charge/config contracts are documented in [External Payment Provider Integration Readiness](./payment-providers.md).

### `GET /api/v1/billing/payments/{payment}`

- Purpose: payment details.
- Scope: owner or elevated view permission.
- Response: safe fields + safe metadata.

### `GET /api/v1/billing/payments/{payment}/status`

- Purpose: lightweight polling status.
- Response: status + relevant timestamps + safe failure reason.

## Payment Simulation Endpoints

### `POST /api/v1/billing/payments/{payment}/simulate/success`

- Purpose: simulate success (admin/demo operation).
- Permission: `billing.payments.simulate`.
- Behavior: validate state -> set succeeded -> append transaction -> queue side effects/webhook.
- Idempotency: not required; state machine guards repeats.

### `POST /api/v1/billing/payments/{payment}/simulate/failure`

- Purpose: simulate failure (admin/demo operation).
- Permission: `billing.payments.simulate`.
- Body: `failure_reason` (safe enum), optional note/metadata.
- Behavior: validate state -> set failed -> append transaction -> queue webhook.

## Payment Transactions Endpoint

### `GET /api/v1/billing/payments/{payment}/transactions`

- Purpose: payment timeline/history.
- Query: optional `type`, pagination.
- Scope: owner or elevated view permission.
- Response excludes sensitive payload internals.

## Payment Webhooks Endpoint

### `GET /api/v1/billing/payments/{payment}/webhooks`

- Purpose: list webhook deliveries related to payment.
- Query: optional `status`, `event`, pagination.
- Scope: owner or `billing.webhooks.view`.
- Response: delivery summary/status/attempt timings.

## Webhook Delivery Retry Endpoint

### `POST /api/v1/billing/webhooks/{webhookDelivery}/retry`

- Purpose: manual webhook retry.
- Permission: `billing.webhooks.retry`.
- Body: optional reason.
- Behavior: allow only retry-eligible statuses; queue retry job; audit event.

## Activity Logging Expectations

Log:
- subscription creation/change/cancel
- payment created/succeeded/failed/retry
- webhook retry requested
- idempotency conflict/replay events where useful

Do not log:
- sensitive payload data/secrets
- high-noise read-only events (e.g., plan list, usage view)

## Queue / Async Behavior

- Payment creation is synchronous DB operation.
- Webhook sending is async.
- Simulation endpoints return after state transition + queue dispatch.
- Webhook retry endpoint dispatches async job.

## Security Notes

- No real card data.
- Payment methods store only simulator-safe masked data and last4.
- No provider secrets in payload/response logs.
- Restrict simulator endpoints to elevated permissions.
- Treat idempotency keys as operational identifiers (do not over-log raw values).
- Prefer public IDs for external-facing paths/payloads.

## Non-Goals For This Phase

- No route/controller/request/resource/service/model/migration implementation.
- No runtime contract enforcement yet.
- No provider integration.

## Implementation Notes For Next Phases

- Next phase should formalize enum/status sets and transition rules.
- After enums, implement routes/controllers/FormRequests/DTO/services incrementally.
- Keep API envelope and error code stability from first implementation commit.
- Enum/status planning details: [Enums & Statuses Planning](./statuses.md).

## Status

- Phase 6 is API contract/documentation only.
- No routes, controllers, requests, resources, services, models, or migrations have been created yet.
- Next phase: Enums & Statuses.

## API Examples

Create payment request:

```json
{
  "subscription_public_id": "sub_01H...",
  "currency": "USD",
  "payment_method": "fake_card",
  "metadata": {
    "source": "subscription_upgrade"
  }
}
```

Create payment response:

```json
{
  "success": true,
  "message": "Payment created successfully.",
  "data": {
    "payment": {
      "public_id": "pay_01H...",
      "status": "pending"
    },
    "simulator_actions": [
      "simulate_success",
      "simulate_failure"
    ]
  },
  "meta": {}
}
```

Idempotency conflict response:

```json
{
  "success": false,
  "message": "Idempotency conflict.",
  "errors": {},
  "code": "idempotency_conflict"
}
```

Simulate success response:

```json
{
  "success": true,
  "message": "Payment marked as succeeded.",
  "data": {
    "payment": {
      "public_id": "pay_01H...",
      "status": "succeeded"
    }
  }
}
```

Payment status response:

```json
{
  "success": true,
  "message": "Payment status fetched.",
  "data": {
    "public_id": "pay_01H...",
    "status": "failed",
    "failure_reason": "card_declined"
  }
}
```

Usage response:

```json
{
  "success": true,
  "message": "Usage fetched.",
  "data": [
    {
      "feature_key": "chat.messages.monthly",
      "used": 120,
      "limit": 500,
      "remaining": 380,
      "reset_at": "2026-07-01T00:00:00Z"
    }
  ]
}
```
