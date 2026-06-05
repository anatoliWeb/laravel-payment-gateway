# Payment Simulation Flow

Payment simulation dispatches billing domain events after successful state changes:
`PaymentSucceeded` for simulator success and `PaymentFailed` for simulator failure.
Repeated final-state no-ops do not dispatch duplicate events.

## Purpose

Phase 15 adds simulator-safe payment status transitions for demo payment attempts.

It lets an authorized operator mark eligible simulator/manual/internal payments as succeeded or failed while preserving row locking, immutable final states, transaction history, activity logs, and ownership scope.

## Non-Goals

This phase does not:
- call real external payment providers
- execute real charges
- deliver webhooks
- activate or renew subscriptions
- implement refunds
- change provider factory/runtime behavior
- change ownership scope rules
- add frontend screens

## Simulator-Only Behavior

Only payments using demo-safe providers are simulatable:
- `simulator`
- `manual`
- `internal_wallet`

Payments with real/external provider keys such as `stripe`, `paypal`, `liqpay`, or bank adapters return `payment_not_simulatable`.

## Status Transition Rules

Allowed Phase 15 transitions:
- `pending -> succeeded`
- `pending -> failed`
- `processing -> succeeded`
- `processing -> failed`

The documented broader matrix still reserves `expired` and `cancelled` for future scheduler/admin flows.

Final statuses:
- `succeeded`
- `failed`
- `expired`
- `cancelled`

Final states are immutable. A repeated call for the same final target returns the current payment without appending another transaction. A call that tries to move one final state into another returns `payment_already_final`.

## Success Simulation

`POST /api/v1/billing/payments/{payment}/simulate/success`

Requires:
- authentication
- `billing.payments.simulate`

Effects:
- locks the payment row
- changes status to `succeeded`
- sets `paid_at`
- appends a `payment_succeeded` transaction
- writes `billing.payment_simulated_success` activity log

## Failure Simulation

`POST /api/v1/billing/payments/{payment}/simulate/failure`

Requires:
- authentication
- `billing.payments.simulate`
- safe `reason`

Effects:
- locks the payment row
- changes status to `failed`
- sets `failed_at`
- stores `failure_reason`
- appends a `payment_failed` transaction
- writes `billing.payment_simulated_failure` activity log

## Final Status Protection

`PaymentSimulationService` treats `succeeded`, `failed`, `expired`, and `cancelled` as final.

Repeated same-target simulation is a no-op to avoid duplicate transaction or activity side effects. Conflicting final transitions are rejected.

## Payment Transaction History

Every valid transition appends an immutable `payment_transactions` row with:
- transition type
- previous status
- new status
- amount and currency snapshot
- safe message
- safe payload with actor, provider, payer, company, and seller context

Unsafe metadata is stripped from service payloads and rejected at the request layer.

## Activity Logging

Activity actions:
- `billing.payment_simulated_success`
- `billing.payment_simulated_failure`

Activity metadata includes payment, actor, status, amount, currency, provider, provider reference, payer, company, and seller identifiers when present.

It does not log raw card data, idempotency keys, credentials, provider secrets, tokens, passwords, or private keys.

## Permissions

Simulation endpoints are protected by:

`billing.payments.simulate`

The permission is seeded for admin and is not assigned to the normal user role by default. Payment ownership alone does not allow simulation.

## Ownership Scope

Simulation preserves:
- `payer_user_id`
- `company_id`
- `seller_id`
- `provider_account_id`
- `ownership_metadata`

These fields are needed later for reporting and webhook routing.

## Relationship With Idempotency

Payment creation remains protected by `Idempotency-Key`.

Simulation endpoints do not create new payments, charges, wallet debits, or wallet credits. Their safety boundary is the row lock, transition matrix, final-state no-op behavior, and transaction history.

## Relationship With Webhooks

Phase 16 dispatches outbound webhook delivery jobs after successful simulator state transitions:
- `payment.succeeded`
- `payment.failed`

Repeated same-target final simulation remains a no-op and does not create duplicate webhook deliveries.

Webhook delivery details are documented in [Webhook Delivery Flow](./webhooks.md).

## Relationship With Subscription Activation

Phase 15 does not activate, renew, upgrade, or cancel subscriptions.

Successful payment simulation leaves linked subscriptions unchanged. Subscription side effects remain a later phase.

## Testing Strategy

Tests cover:
- permission-protected success simulation
- permission-protected failure simulation
- owner without simulate permission denied
- status changes and timestamps
- transaction history rows
- final-state immutability
- repeated same-target simulation without duplicate side effects
- unsafe metadata validation
- external-provider protection
- ownership preservation
- subscription non-activation
- existing payment/idempotency/provider/RBAC/ownership regression

## Status

Phase 15 payment simulation flow is implemented for simulator-safe payment attempts.
