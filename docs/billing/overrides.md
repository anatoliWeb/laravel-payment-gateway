# Billing Overrides & Restrictions

## Purpose

Billing overrides and restrictions let operators apply manual access decisions without changing shared plan definitions.

This layer is module-agnostic. Chat and future dialer modules should call Billing services, especially `FeatureAccessService`, instead of implementing their own override or blacklist rules.

## Concepts

- `billing_restrictions` stores user-level billing, payment, and feature blocks.
- `feature_overrides` stores user-level or subscription-level feature exceptions.
- Restrictions are evaluated before overrides.
- Overrides are evaluated before plan features.
- Plan features remain the default source of truth for normal users.

## Billing Restrictions

Billing restrictions are manual user-level blocks.

Supported planned types:
- `billing_blocked`: blocks billing access generally.
- `payment_blocked`: blocks payment creation checks, but does not automatically block feature access.
- `feature_blocked`: blocks one feature key only.

Restrictions support active/inactive state, optional start/end windows, reason metadata, and optional admin/operator creator references.

## Payment Restrictions

Payment restrictions are represented by `payment_blocked` records in `billing_restrictions`.

They are intentionally separate from the Phase 13.1 payment risk guard. A manual payment blacklist is a direct operator decision; risk guard rules are automated simulator safety rules.

## Feature Overrides

Feature overrides allow user-specific or subscription-specific exceptions.

Typical uses:
- manually enable a feature for one subscription
- disable a feature for one user
- raise or lower a numeric limit
- apply a temporary access exception
- document an admin/manual reason

## Override Priority

Resolution order:

1. Active billing restriction
2. Active feature restriction
3. Active subscription-level feature override
4. Active user-level feature override
5. Plan feature fallback

Within the same owner level, higher `priority` wins. When priority is equal, newest row wins.

## Expiration Rules

Restrictions and overrides are active only when their start/end window includes the current time. Expired rows remain in the database for auditability and explainability.

## Activity Logging Notes

Activity logging for restrictions and overrides is planned for the dedicated Activity Logging phase.

Expected future events:
- `billing.restriction.created`
- `billing.restriction.expired`
- `billing.override.created`
- `billing.override.expired`
- `billing.access.blocked`

Phase 10.1 stores enough metadata to support these logs later, but does not create listeners, jobs, controllers, or admin UI.

## Non-Goals

- No admin UI.
- No payment creation flow.
- No payment risk/fraud guard implementation.
- No idempotency runtime logic.
- No chat-specific integration.
- No dialer-specific integration.
- No real bank-grade antifraud system.

## Status

Phase 10.1 implements the persistence and service-layer foundation for billing restrictions and feature overrides.

Payment risk and fraud guard remains a separate Phase 13.1 concern.
