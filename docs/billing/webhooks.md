# Webhook Delivery Flow

## Purpose

Phase 16 implements outbound billing webhooks for payment lifecycle events.

Outbound webhooks notify a client-owned callback URL after a billing event is persisted. Delivery is asynchronous, retryable, signed, and recorded in `webhook_deliveries`.

## Non-Goals

This phase does not connect real payment providers, execute external charges, implement real inbound provider webhook endpoints, activate subscriptions, add reports API/UI, or change frontend/chat/realtime/notification flows.

## Outbound vs Inbound Webhooks

Outbound webhooks are callbacks from our system to a client URL. Phase 16 implements outbound `payment.succeeded` and `payment.failed` delivery.

Inbound provider webhooks are callbacks from an external provider into our system. They require provider-specific signature verification and remain future work because real provider integrations are disabled in demo mode.

## Events

Supported event names:
- `payment.created`
- `payment.succeeded`
- `payment.failed`
- `payment.expired`
- `payment.cancelled`

Implemented runtime integration:
- `payment.succeeded`
- `payment.failed`

## Payload Format

Payload includes safe fields: `event_id`, `event_type`, `occurred_at`, payment id/uuid/status, amount, currency, provider, provider reference, payer user id, company id, seller id, and sanitized metadata.

Payload excludes raw idempotency keys, idempotency key hashes, provider secrets, encrypted credentials, raw card data, CVV/CVC/security code, tokens, passwords, and private keys.

## Signature

Payloads are signed with HMAC SHA-256 over the JSON payload.

Secret resolution:
- `BILLING_WEBHOOK_SECRET`
- fallback to `APP_KEY` for local/demo safety

Headers:
- `X-Billing-Event`
- `X-Billing-Delivery`
- `X-Billing-Signature`
- `X-Billing-Timestamp`

## Delivery Statuses

Delivery records use `pending`, `processing`, `delivered`, `retrying`, and `permanently_failed`. The service also accepts `failed` as a retryable legacy/intermediate state.

## Queue Job

`SendWebhookDeliveryJob` reloads the delivery, skips terminal records, marks processing, increments attempts, sends HTTP POST with signed headers, marks delivered on 2xx, schedules retry on non-2xx/exception, and marks permanently failed after max attempts.

## Retry Policy

Max attempts default to `5`. Backoff starts at 30 seconds, doubles by attempt, and caps at 300 seconds.

## Manual Retry API

`POST /api/v1/billing/webhooks/{webhookDelivery}/retry`

Requires `billing.webhooks.retry`.

Allowed retry statuses: `failed`, `retrying`, `permanently_failed`.

Rejected statuses: `pending`, `processing`, `delivered`.

## Payment Webhook Listing API

`GET /api/v1/billing/payments/{payment}/webhooks`

Requires `billing.webhooks.view`.

The response exposes safe delivery history only and does not return full callback URLs, signatures, or secrets.

## Payment Simulation Integration

Successful simulation creates and dispatches `payment.succeeded`.

Failed simulation creates and dispatches `payment.failed`.

Repeated same-target final simulation remains a no-op and does not create duplicate webhook deliveries.

If a payment has no `callback_url`, no delivery record is created.

## Ownership Scope

Webhook payloads include `payer_user_id`, `company_id`, and `seller_id` when present. Listing and retry endpoints use the existing payment ownership access service in addition to route permissions.

## Idempotency Relationship

Payment creation idempotency still prevents duplicate payment attempts. Payment simulation uses row locks and final-state no-op behavior to prevent duplicate final transition side effects, including duplicate webhook delivery records.

## Security and Metadata Safety

Rules:
- do not log webhook secrets
- do not expose full callback URLs through delivery resources
- do not store raw card data or provider credentials in payload
- truncate response body before storage
- store callback host for diagnostics instead of full URL in activity metadata

## Testing Strategy

Tests cover delivery creation, safe payloads, signatures, job delivery outcomes, manual retry, safe listing, and duplicate prevention. Tests use Laravel HTTP fake and must not call real external URLs.

## Status

Phase 16 outbound webhook delivery flow is implemented at service/job/API level.

Real inbound provider webhooks remain future provider-specific work.
