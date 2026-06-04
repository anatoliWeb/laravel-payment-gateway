# Permission-Gated Wallet Adjustments API

## Purpose

Phase 13.3.1 exposes manual wallet adjustment as a sensitive billing operation.

The endpoint is available only to an authenticated actor with the required wallet adjustment permission. The `admin` role currently receives these permissions through `BillingPermissionSeeder`, but the operation is not tied to an admin namespace or admin role check. Future support or operator roles can receive the same granular permissions.

## Non-Goals

This operation does not:
- expose a public user wallet debit endpoint
- allow an authenticated user to adjust a balance without permission
- replace wallet top-up, wallet payment, refund, or payment creation flows
- call external payment providers
- activate or renew subscriptions
- mutate wallet balances outside the ledger

## Endpoint

### `POST /api/v1/billing/wallet-adjustments`

Required headers:
- `Authorization: Bearer <token>`
- `Idempotency-Key: <unique-operation-key>`

Request body:

```json
{
  "user_id": 42,
  "currency": "USD",
  "amount": 2500,
  "direction": "credit",
  "reason": "Support-approved balance correction",
  "description": "Customer billing reconciliation.",
  "reference": "ticket-1001",
  "metadata": {
    "case_type": "support"
  }
}
```

Amounts are stored in minor currency units. `reason` and `Idempotency-Key` are mandatory.

## Permissions

The route requires at least one of:
- `billing.wallets.adjust`
- `billing.wallets.credit`
- `billing.wallets.debit`

The controller then enforces the requested direction:
- credit requires `billing.wallets.credit` or `billing.wallets.adjust`
- debit requires `billing.wallets.debit` or `billing.wallets.adjust`

Authentication alone is insufficient. A normal user without an adjustment permission receives `403`.

## Credit Operation

A permitted credit:
- increases the target wallet's available balance
- appends a completed `adjustment` transaction with `direction=credit`
- stores before/after snapshots
- emits `billing.wallet_manual_credit`

## Debit Operation

A permitted debit:
- validates the target wallet's available balance
- decreases available balance only when sufficient funds exist
- appends a completed `adjustment` transaction with `direction=debit`
- emits `billing.wallet_manual_debit`

Insufficient funds return `insufficient_wallet_balance` and do not create an adjustment transaction.

## Actor vs Target User

The actor is the authenticated user authorized to perform the financial operation.

The target user owns the wallet being adjusted. Actor and target user IDs are stored as safe audit metadata. They may be the same user only when that actor has the required permission.

## Idempotency

The idempotency key is unique per wallet operation.

Repeating the same key with the same currency, direction, and amount returns the existing ledger entry without changing the balance or duplicating the activity log.

Reusing the key for a different adjustment returns `idempotency_key_conflict`.

Phase 14 stores a central actor-scoped `wallet.adjustment` record before ledger mutation. The derived local wallet transaction key remains a second safety layer.

## Ledger Safety

The API never mutates a wallet balance directly.

`WalletTransactionService`:
1. resolves the target user's active currency balance
2. locks the wallet for cross-currency idempotency and the affected balance row
3. validates debit funds
4. updates the balance in a database transaction
5. appends a completed ledger entry
6. stores available and held balance snapshots

## Activity Logging

Successful operations emit:
- `billing.wallet_manual_credit`
- `billing.wallet_manual_debit`

Activity metadata contains safe actor, target, amount, reason, reference, transaction, and balance snapshot context. Activity logging failure does not roll back a valid ledger operation.

## Metadata Safety

The request rejects raw payment data and secret-like fields, including nested metadata keys:
- card number or PAN
- CVV/CVC/security code
- token
- secret
- password
- private key

The response hides the raw idempotency key and exposes wallet transaction metadata through a safe allowlist.

## Relationship With Payment Sources

Manual wallet adjustment is a special billing operation and a future payment-source authorization example. It does not create a `Payment` or `PaymentAttempt`.

Wallet top-up, wallet payment, saved payment method use, manual invoice, simulator operations, and external providers have separate business semantics. Sensitive source/provider use may require its own permission in later hardening phases.

## Future Provider Permission Model

Planned naming examples:
- `billing.payment_sources.use.wallet`
- `billing.payment_sources.use.payment_method`
- `billing.payment_sources.use.manual_wallet_adjustment`
- `billing.payment_sources.use.manual_invoice`
- `billing.payment_sources.use.simulator`
- `billing.payment_sources.use.external_provider`
- `billing.providers.use.simulator`
- `billing.providers.use.<provider>`

Current simulator/internal/manual source-provider readiness permissions are seeded for admin but not enforced on normal payment flows. Future real-provider permissions remain documentation-only. A provider permission would be an additional authorization check, not a replacement for ownership, risk guards, provider configuration validation, or idempotency.

## Testing Strategy

Targeted tests cover:
- credit-only, debit-only, and generic adjust permissions
- authenticated user without permission
- seeded admin access through permissions
- removal of the old admin namespace endpoint
- required reason and idempotency
- unsafe metadata rejection
- ledger snapshots and insufficient debit
- idempotent replay and conflict detection
- activity logging

## Status

Phase 13.3.1 implements permission-gated wallet adjustments at `POST /api/v1/billing/wallet-adjustments`.
