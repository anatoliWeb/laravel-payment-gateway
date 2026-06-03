# Payment Methods & User Payment Preferences

## Purpose

Payment methods and user payment preferences define how future billing flows may choose a simulated billing instrument.

Phase 12.3 stores simulator-safe payment methods, default method selection, payment strategy, and explicit consent timestamps. It does not execute payments, wallet debits, card charges, auto top-up, or provider calls.

## Non-Goals

This phase does not implement:
- payment creation flow
- wallet/card payment API
- auto top-up execution
- autopay execution
- real Stripe, PayPal, LiqPay, WayForPay, or bank provider integration
- raw card storage
- controllers, routes, FormRequests, resources, or jobs
- frontend changes

## Simulated Payment Methods

Supported method types:
- `fake_card`: simulator card-like billing instrument
- `fake_manual_invoice`: manual invoice approval path
- `fake_wallet`: internal wallet balance method placeholder

Supported providers:
- `simulator`
- `manual`
- `internal_wallet`

Supported statuses:
- `active`
- `inactive`
- `expired`
- `revoked`
- `failed`

Payment methods are not PCI vault records. They store only safe display and routing fields.

## User Payment Strategies

Supported strategies:
- `wallet_only`: use internal balance only in future payment flow
- `payment_method_only`: use default saved payment method only
- `wallet_first`: try wallet first, then payment method fallback in future flow
- `manual_invoice`: require manual invoice approval path

Strategies are preference data only. They do not trigger payment attempts in Phase 12.3.

## Default Payment Method

`payment_methods.is_default` marks the preferred method for a user.

`user_payment_preferences.default_payment_method_id` stores the same selection from the preference side. `PaymentMethodService` keeps both values synchronized when setting or deactivating a default method.

Only one method should be default per user at service level.

## Consent Model

Consent is explicit and separated by responsibility:
- `payment_methods.consent_given_at`: user consented to save/use the simulated payment method
- `user_payment_preferences.auto_charge_consent_at`: user consented to future automatic charges
- `user_payment_preferences.auto_top_up_consent_at`: user consented to future automatic top-up

Saving a payment method does not imply auto charge permission.

## Auto Charge Consent

`auto_charge_enabled` and `auto_charge_consent_at` are preference flags for future payment orchestration.

Phase 12.3 does not charge cards or create payment attempts when auto charge is enabled.

## Auto Top-Up Consent

`auto_top_up_enabled`, threshold amount, top-up amount, limits, currency, and consent timestamp prepare for future wallet top-up rules.

Phase 12.3 does not execute top-up, wallet credit, card charge, or queue jobs.

Actual auto top-up remains Phase 13.2.

Auto top-up and auto charge foundation is documented in [Auto Top-Up & Auto Charge](./auto-top-up.md).

## Masking Rules

Rules:
- never store raw card number
- never store CVV/CVC/security code
- store `last4` only
- use safe labels such as `Visa ending 4242`
- provider references are fake simulator references only

## Metadata Rules

Metadata may include safe diagnostics:
- `source`
- `simulator_safe`
- safe user preference context
- safe correlation identifiers

Metadata must not include:
- raw card numbers
- CVV/CVC/security codes
- provider secrets
- tokens
- passwords
- real payment credentials

## Wallet Integration Readiness

The `fake_wallet` method points future payment orchestration toward the internal wallet balance foundation from Phase 12.2.

No wallet debit, hold, release, refund, or top-up is performed by payment method services in this phase.

Wallet foundation details: [User Wallet Balance](./wallets.md).

## Payment API Readiness

Future payment APIs can use:
- selected default payment method
- payment strategy
- explicit auto charge consent
- explicit auto top-up consent
- method status and provider

Actual wallet/card payment API remains Phase 13.3.

## Testing Strategy

Tests cover:
- model relations and casts
- raw card columns are absent
- fake card creation stores only last4/masked display data
- manual invoice and wallet method creation
- default method selection and previous default clearing
- ownership protection
- method deactivation
- preference creation
- strategy validation
- auto charge consent toggling
- auto top-up consent, currency validation, and no side effects

## Status

Phase 12.3 implements payment method and user payment preference foundation.

It is simulator-safe and intentionally does not implement runtime payment execution.
