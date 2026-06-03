# User Wallet Balance

## Purpose

User wallets provide an internal server-side balance that future billing flows can use before or alongside simulated card/payment-method charges.

Phase 12.2 implements wallet persistence, multi-currency balances, ledger transactions, and service-layer balance operations. It does not implement payment creation or public wallet API endpoints.

## Non-Goals

This phase does not implement:
- card/payment method flow
- payment creation flow
- wallet payment API
- auto top-up
- autopay
- external payment provider behavior
- controllers, routes, FormRequests, resources, or jobs
- frontend changes

## Wallet Model

The `wallets` table stores one wallet per user.

Key fields:
- `uuid`: public wallet identifier
- `user_id`: unique owner user
- `status`: `active`, `suspended`, or `closed`
- `metadata`: safe extension payload

Wallet creation is handled by `WalletService`; the `User` model only exposes a relation and does not auto-create wallets.

## Wallet Balances

The `wallet_balances` table stores one balance per wallet and currency.

Amounts are stored in minor units:
- `available_amount`: spendable balance
- `held_amount`: reserved balance

The unique `(wallet_id, currency_id)` constraint prevents duplicate balances for the same currency.

## Wallet Transactions

The `wallet_transactions` table is the wallet ledger.

Supported types:
- `top_up`
- `debit`
- `hold`
- `release`
- `refund`
- `adjustment`

Supported directions:
- `credit`
- `debit`
- `neutral`

Supported statuses:
- `pending`
- `completed`
- `failed`
- `cancelled`

Transactions store before/after available and held balance snapshots for auditability.

## Multi-Currency Support

Wallets support multiple currencies through `wallet_balances.currency_id`.

The currency catalog comes from Phase 12.1. Wallet services only operate on active currencies.

## Available vs Held Balance

Available balance can be spent.

Held balance is reserved. Phase 12.2 supports moving funds from available to held and releasing held funds back to available.

## Transaction Types

Service-layer operations:
- credit/top-up increases available balance
- debit decreases available balance
- hold moves available to held
- release moves held to available
- refund increases available balance

Adjustment is supported by the ledger schema/factory as a future operator action, but no admin API is implemented in this phase.

## Idempotency Notes

`WalletTransactionService` includes a local idempotency guard.

If a completed wallet transaction already exists for the same wallet and idempotency key, the existing transaction is returned and the balance is not changed again.

This does not replace the full payment idempotency layer planned for Phase 14.

## Payment Integration Readiness

Wallet transactions can optionally reference:
- `payment_id`
- `subscription_id`
- `reference_type`
- `reference_id`

Future payment flows can link wallet debits to payment attempts without changing the wallet ledger schema.

Payment method and preference selection is documented in [Payment Methods & User Payment Preferences](./payment-methods.md).

## Auto Top-Up Readiness

The wallet foundation can support auto top-up later, but no automatic charging, consent handling, or payment-method fallback is implemented in this phase.

Auto top-up remains Phase 13.2.

Auto top-up and auto charge foundation is documented in [Auto Top-Up & Auto Charge](./auto-top-up.md).

## Testing Strategy

Tests cover:
- wallet, balance, transaction relations
- one wallet per user
- one balance per wallet/currency
- inactive/missing currency handling
- credit/debit/hold/release/refund operations
- local idempotency guard
- before/after balance snapshots
- multi-currency isolation

## Status

Phase 12.2 implements user wallet balance foundation.

Runtime wallet activity logs are intentionally left for Phase 20 Activity Logging.
