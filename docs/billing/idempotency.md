# Idempotency Support

## Purpose

Phase 14 adds a central idempotency registry for sensitive billing write operations.

Idempotency prevents repeated requests from creating duplicate payments, provider-like simulator charges, wallet debits, wallet top-ups, wallet adjustments, auto top-ups, or auto charges.

It does not replace permission checks, ownership checks, payment risk checks, provider configuration validation, or local wallet ledger idempotency.

## Non-Goals

This phase does not:
- call real external payment providers
- implement payment success/failure simulation
- deliver webhooks
- activate or renew subscriptions
- expose idempotency management API endpoints
- enforce payment-source/provider readiness permissions on normal payment flows

## Idempotency Key

Clients provide `Idempotency-Key` for payment creation, wallet top-up, and wallet adjustment writes.

The registry stores a SHA-256 hash of the normalized key, never the raw client key. Keys are isolated by actor user and operation scope, so different users can safely reuse the same client key.

## Scopes

Implemented scopes:
- `payment.create`
- `wallet.top_up`
- `wallet.adjustment`
- `auto_top_up`
- `auto_charge`

Future-ready scope:
- `provider.charge`

Internal payment and wallet ledger operations use derived hashed keys so nested operations do not collide with public API scopes.

## Payload Fingerprint

`IdempotencyService`:
1. removes raw card, credential, token, secret, and password-like fields
2. recursively sorts associative payload keys
3. serializes the safe normalized payload
4. stores a SHA-256 request hash

Equivalent payloads produce the same fingerprint regardless of associative key order.

## Replay Behavior

Same key, scope, user, and payload:
- `completed`: returns the stored safe resource locator and reconstructs the existing result
- `failed`: replays the stored stable failure code
- `processing`: returns `idempotency_request_processing`
- `expired` or expired TTL: allows a new attempt

Completed response payloads contain safe internal resource locators only. API resources reconstruct the same client response without storing secrets.

## Conflict Behavior

The same key, scope, and user with a different payload hash returns:

`idempotency_key_conflict`

No new side effect is attempted.

## Processing State

New records start in `processing` with a five-minute lock.

Concurrent requests with the same fingerprint are blocked while the lock is active. An expired processing lock can restart safely. The database unique key on `(user_id, key, scope)` protects concurrent inserts.

## Expiration

Records receive a 24-hour TTL.

After `expires_at`, replay stops and the same key/scope can start a new processing lifecycle. Cleanup jobs and management endpoints remain future work.

## Payment Creation

`PaymentService` starts idempotency before risk/business side effects.

On success it stores a relation to the created `Payment`. Replay returns the existing payment and prevents:
- duplicate payment rows
- duplicate wallet debits
- duplicate simulator payment-method charges
- duplicate wallet-first fallback charges

Risk guard still runs for the first attempt. Replayed completed requests do not run risk/provider logic again.

## Wallet Debit

Central `payment.create` idempotency protects the API/payment operation.

The existing wallet transaction idempotency key remains a second ledger-level guard. Central idempotency does not weaken balance locking or append-only wallet history.

## Wallet Top-Up

`wallet.top_up` protects the full top-up orchestration:
- nested payment creation uses a derived key
- wallet credit uses a derived local ledger key
- replay returns the original payment and wallet transaction

The wallet is never credited twice.

## Wallet Adjustments

`wallet.adjustment` is actor-scoped and permission checks still apply before service execution.

Replay returns the original wallet transaction. Conflict prevents a changed amount, direction, target, reason, or safe metadata from reusing the same key.

## Auto Top-Up / Auto Charge

Allowed automatic operations use `auto_top_up` and `auto_charge`.

Eligibility decisions that produce no financial side effect do not create registry records. Successful repeated operations replay their original payment/ledger result.

## Provider Charge Readiness

Future real provider adapters should use `provider.charge`.

Provider idempotency keys must be derived from the internal key or forwarded through an adapter-safe field. Raw unsafe metadata, credentials, card data, and secrets must never be forwarded as idempotency context.

The simulator receives the derived payment idempotency key only on the first attempt because completed replay stops before provider execution.

## Security and Metadata Safety

Stored key and payload safety:
- raw idempotency keys are hashed
- payload fingerprints are deterministic
- response payloads are sanitized
- unsafe nested metadata is removed
- arbitrary exception/SQL messages are not stored as replay error codes

## Stable Error Codes

Implemented:
- `idempotency_key_required`
- `idempotency_key_conflict`
- `idempotency_request_processing`
- `idempotency_replay_resource_missing`
- `idempotency_operation_failed`

Operation-specific failures such as `insufficient_wallet_balance` are stored and replayed when safe.

Reserved duplicate diagnostics such as `duplicate_wallet_debit`, `duplicate_payment_charge`, and `duplicate_auto_top_up` are not emitted because replay prevents the duplicate before it occurs.

## Testing Strategy

Targeted tests cover:
- payment, wallet debit, payment method, and wallet-first replay
- different users reusing the same key
- conflict behavior
- processing locks and expiration restart
- wallet top-up and adjustment replay
- auto top-up and auto charge replay
- permission seeder readiness
- existing payment/wallet/RBAC regression

## Status

Phase 14 central idempotency support is implemented for current billing write and automation flows.
