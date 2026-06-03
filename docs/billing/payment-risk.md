# Payment Risk & Fraud Guard

## Purpose

Payment Risk & Fraud Guard adds a demo-safe safety layer before payment creation.

It prevents obvious unsafe simulator behavior such as manually blocked users, too many failed attempts, excessive payment creation attempts, oversized demo payments, and suspicious request shape.

## Non-Goals

This is not:
- bank-grade antifraud
- real provider fraud scoring
- card network risk processing
- provider abstraction
- idempotency storage/replay
- webhook security
- subscription activation logic

## Demo-Safe Risk Model

The guard runs before wallet debit or simulator payment-method processing.

If a risk rule blocks the request:
- no successful payment is created
- no wallet debit is performed
- no provider-like action is performed
- subscription state is not activated or changed
- a safe activity log is written when possible

## Payment Blacklist

Manual payment blacklist uses `billing_restrictions` with type `payment_blocked`.

`BillingRestrictionService::isPaymentBlocked()` remains the source of truth for this operator-controlled block.

Blocked reason:
- `payment_blocked`

## Failed Attempt Limits

The guard checks failed payments for the user in recent windows.

Current demo defaults:
- max failed payments per hour: 5
- max failed payments per day: 20

Blocked reason:
- `too_many_failed_attempts`

## Payment Attempt Limits

The guard checks total payment creation attempts for the user in recent windows.

Current demo defaults:
- max payment attempts per hour: 20
- max payment attempts per day: 100

Blocked reason:
- `too_many_payment_attempts`

## Demo Amount Limit

The simulator blocks unusually large demo payments.

Current limit:
- `1_000_000` minor currency units

Blocked reason:
- `amount_exceeds_demo_limit`

## Suspicious Activity Flags

The guard intentionally avoids deep security scanning.

Current simple signal:
- unusually large metadata key count

Blocked reason:
- `suspicious_activity`

Risk flags may include:
- `payment_blacklist`
- `failed_attempt_limit`
- `payment_attempt_limit`
- `demo_amount_limit`
- `suspicious_activity`
- `large_metadata`

## Activity Logging

Risk guard writes safe activity events:
- `billing.payment_risk_blocked`
- `billing.payment_suspicious_attempt`

Metadata includes safe fields:
- reason
- risk flags
- amount
- currency
- payment source
- payment method ID when safe

It does not log:
- raw idempotency key
- raw card data
- provider secrets
- raw sensitive metadata

## Relationship With Idempotency

Risk guard does not replace idempotency.

Phase 13.1 checks whether the payment creation attempt is allowed before side effects. Phase 14 still owns full idempotency storage, replay, conflict detection, and expiry.

Wallet debit still uses the existing local wallet transaction idempotency key to avoid duplicate balance mutation.

## Relationship With Provider Integration

Provider-specific risk and fraud behavior remains outside this phase.

Future real provider integrations may expose provider risk signals, error mappings, fraud responses, or webhook events. Those belong to provider-specific adapter work and must not require rewriting the demo-safe risk guard.

## Testing Strategy

Tests cover:
- payment-blocked users
- blocked attempts do not create payments
- blocked attempts do not debit wallets
- failed-attempt limits
- payment-attempt limits
- demo amount limit
- suspicious metadata
- activity logs for blocked/suspicious attempts
- normal payment creation under risk limits

## Status

Phase 13.1 implements a simulator-safe payment risk guard.

It is intentionally small, deterministic, and portfolio-friendly.
