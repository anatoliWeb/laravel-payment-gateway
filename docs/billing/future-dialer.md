# Future Dialer Billing Extension

## Purpose

This document defines how the billing core can be reused by a future dialer/calling module without making Billing depend on dialer tables, SIP logic, call records, or realtime calling infrastructure.

Phase 12 proves the billing model is not chat-only. It documents and validates generic feature keys that future dialer code can consume through `FeatureAccessService`, `UsageLimitService`, feature overrides, and billing restrictions.

## Non-Goals

Phase 12 does not implement:
- dialer module runtime
- SIP accounts or provider integration
- call models
- call controllers or routes
- call events, jobs, or queues
- call recording storage lifecycle
- payment creation flow
- wallet, currency, or autopay logic
- frontend dialer UI

## Reusable Billing Concepts

The future dialer module should reuse:
- `plans` for available packages
- `plan_features` for dialer limits and flags
- `subscriptions` for effective plan resolution
- `feature_usages` for usage counters
- `feature_overrides` for manual exceptions
- `billing_restrictions` for feature-specific or user-level blocks

The billing core remains module-agnostic because services accept a `feature_key` string and do not need dialer-specific models.

## Dialer Feature Keys

Core future dialer feature keys:
- `dialer.calls.monthly`
- `dialer.concurrent_calls`
- `dialer.sip_accounts`
- `dialer.recordings.storage_mb`
- `dialer.recordings.retention_days`
- `dialer.webhook_endpoints.count`
- `dialer.webhook_deliveries.monthly`
- `dialer.analytics.enabled`
- `dialer.call_recording.enabled`

These are billing feature keys only. They do not imply that a dialer runtime exists in this project yet.

## Plan Feature Examples

Example plan behavior:
- Free: no dialer calls, no SIP accounts, no call recording.
- Basic: small monthly call quota and one SIP account for future demo usage.
- Pro: higher call quota, limited concurrent calls, analytics, and call recording enabled.
- Enterprise: high limits and longer retention.
- Demo Enterprise: very high portfolio/demo limits.

The exact limits live in `BillingSeeder` as seeded `plan_features` rows.

## Usage-Based Limits

Usage-style features should be checked with `UsageLimitService`:
- `dialer.calls.monthly`
- `dialer.recordings.storage_mb`
- `dialer.webhook_deliveries.monthly`

Future dialer code should increment usage only after a billable action succeeds, for example after a call is accepted or after a webhook delivery is queued.

## Recording Storage / Retention

Recording storage and retention are represented as plan features:
- `dialer.recordings.storage_mb`
- `dialer.recordings.retention_days`

Actual cleanup, storage measurement, and file lifecycle should be implemented by the future dialer module and scheduler jobs. Billing only stores the configured limit.

## Webhook Limits

Dialer webhook capacity is modeled with:
- `dialer.webhook_endpoints.count`
- `dialer.webhook_deliveries.monthly`

Endpoint count is a current-state limit. Delivery count is a usage limit.

## Feature Overrides

Operators can override dialer features with `feature_overrides`.

Examples:
- temporarily raise `dialer.calls.monthly`
- enable `dialer.analytics.enabled` for one user
- lower `dialer.concurrent_calls` for a risky account

Overrides remain generic and are resolved by feature key.

## Billing Restrictions

Operators can block a single dialer feature through `billing_restrictions` with type `feature_blocked`.

Example:
- block `dialer.calls.monthly` while keeping `dialer.analytics.enabled` available.

This is intentionally separate from payment blocking and does not require a dialer-specific restriction table.

## Future Integration Flow

Expected future flow:

1. Dialer endpoint receives a request.
2. Dialer service resolves the authenticated user and intended billable action.
3. Dialer service calls `FeatureAccessService` or `UsageLimitService` with a dialer feature key.
4. If denied, API returns a stable limit/access error.
5. If allowed, dialer runtime performs the call/webhook/storage action.
6. After success, dialer service increments usage when the feature is usage-based.
7. Activity logging records significant billing or limit events.

Billing should not query dialer tables directly.

## Testing Strategy

Billing tests should prove:
- dialer boolean features can be enabled or disabled through plan features
- monthly dialer usage limits allow and deny correctly
- feature overrides work for dialer keys
- feature restrictions can block one dialer feature without blocking all billing
- existing chat billing tests still pass

No dialer models, routes, or controllers are required for these tests.

## Status

This phase documents and validates billing reuse for a future dialer module.

No dialer/calling runtime code has been implemented.

Billing core remains module-agnostic.
