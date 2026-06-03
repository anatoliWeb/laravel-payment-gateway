# Auto Top-Up & Auto Charge

## Purpose

Auto top-up and auto charge provide a simulator-safe billing automation foundation.

The implementation uses existing user payment preferences, wallet balances, payment methods, payment creation, and risk guard services. It does not connect to real payment providers.

## Non-Goals

This phase does not implement:
- real Stripe, PayPal, LiqPay, WayForPay, or bank charges
- provider abstraction
- full idempotency replay/conflict storage
- webhook delivery
- subscription activation or renewal
- frontend settings screens

## Consent Model

Auto charge and auto top-up require explicit consent.

Auto charge requires:
- `auto_charge_enabled = true`
- `auto_charge_consent_at` set
- default active payment method

Auto top-up requires:
- `auto_top_up_enabled = true`
- `auto_top_up_consent_at` set
- matching auto top-up currency
- default active payment method

Auto top-up does not require `auto_charge_enabled`; it has a separate consent timestamp and settings.

## Auto Top-Up Flow

`AutoTopUpService` checks whether the user's wallet balance is at or below the configured threshold.

If allowed, it:
1. creates a simulator-safe payment method payment through `PaymentService`
2. credits the wallet through `WalletTransactionService`
3. stores safe metadata with `source = auto_top_up`
4. writes activity logs

This is a portfolio simulator behavior. Real provider settlement remains future provider work.

## Auto Charge Flow

`AutoChargeService` validates consent, default payment method, amount, and currency.

If allowed, it creates a simulator-safe payment method payment through `PaymentService`.

It does not activate subscriptions or renew plans.

## Wallet Threshold Rules

Auto top-up runs only when current available wallet balance is less than or equal to `auto_top_up_threshold_amount`.

If no wallet balance exists yet, available balance is treated as zero.

## Daily / Monthly Limits

Limits come from:
- `max_auto_top_up_per_day`
- `max_auto_top_up_per_month`

Null means no limit.

The implementation counts completed wallet `top_up` transactions with metadata `source = auto_top_up` in the last day or month.

## Risk Guard Integration

Automation does not bypass `PaymentRiskService`.

Payment creation still runs through `PaymentService`, so payment blocks, failed-attempt limits, payment-attempt limits, demo amount limits, and suspicious activity checks remain active.

## Idempotency Notes

Full idempotency storage/replay/conflict behavior remains Phase 14.

Auto top-up passes an idempotency key to wallet credit as `auto_top_up:{key}` so duplicate balance mutation is guarded locally by `WalletTransactionService`.

## Activity Logging

Activity events:
- `billing.auto_top_up_attempted`
- `billing.auto_top_up_succeeded`
- `billing.auto_top_up_failed`
- `billing.auto_charge_attempted`
- `billing.auto_charge_succeeded`
- `billing.auto_charge_failed`
- `billing.auto_charge_consent_required`

Logged metadata is safe and excludes raw card data, raw idempotency keys, and provider secrets.

## Simulator-Only Provider Behavior

Automation uses existing fake payment methods and simulator-safe payment creation.

No external provider calls are made in this phase.

## Relationship With Subscription Renewal

Auto charge can create a payment, but it does not activate, renew, or upgrade subscriptions.

Subscription renewal and activation remain Phase 19.

Manual wallet top-up and runtime payment preference endpoints are documented in [Wallet/Card Payment API Interface](./payment-api.md).

## Testing Strategy

Tests cover:
- disabled auto top-up/charge
- missing consent
- threshold checks
- default payment method requirements
- inactive payment method handling
- daily/monthly top-up limits
- payment-blocked users
- successful simulator top-up wallet credit
- successful simulator auto charge payment
- activity logs

## Status

Phase 13.2 implements auto top-up and auto charge foundation in simulator-safe mode.
