# Subscription Lifecycle

## Purpose

Phase 19 implements the runtime subscription lifecycle on top of the existing billing, payment, wallet, invoice, event, and scheduler foundations.

The lifecycle is simulator-safe: it models real SaaS billing decisions without calling Stripe, PayPal, LiqPay, banks, or any other external provider.

## Core Rules

- A paid subscription starts as `pending`.
- Access is granted only after a linked payment reaches `succeeded`.
- A failed initial payment keeps the subscription inactive.
- A failed renewal moves an existing active/trialing subscription to `past_due`.
- Cancellation can be immediate or scheduled for the current period end.
- Expiration is handled by the scheduler when no renewal succeeds.
- Wallet and saved payment method renewals use the existing payment and preference services.
- Plan changes are auditable and do not delete historical payment/invoice data.

## Implemented Components

- `SubscriptionLifecycleService`
- `SubscriptionController`
- `SubscriptionResource`
- `StoreSubscriptionRequest`
- `ChangeSubscriptionPlanRequest`
- `CancelSubscriptionRequest`
- `ActivateSubscriptionAfterPaymentSucceeded`
- `MarkSubscriptionPaymentFailed`
- subscription routes under `/api/v1/billing/subscriptions`
- subscription lifecycle activity logs
- scheduler integration for expiration and renewal attempts

## API Endpoints

All endpoints require `auth:sanctum`.

### Create Subscription

`POST /api/v1/billing/subscriptions`

Headers:
- `Idempotency-Key`: required for paid subscription payment creation

Body:

```json
{
  "plan_slug": "pro",
  "payment_source": "payment_method",
  "payment_strategy": "payment_method_only",
  "payment_method_id": 123,
  "callback_url": "https://example.test/billing/callback",
  "metadata": {
    "source": "demo"
  }
}
```

Behavior:
- creates a `pending` subscription
- creates a linked payment when the plan price is greater than zero
- replays the same subscription/payment for the same idempotency key and payload
- does not activate the subscription until payment success

### Show Subscription

`GET /api/v1/billing/subscriptions/{subscription}`

Users may view their own subscription. Admin/company/seller scoped permissions can view broader billing data.

### Change Plan

`POST /api/v1/billing/subscriptions/{subscription}/change-plan`

Body:

```json
{
  "plan_slug": "basic",
  "direction": "downgrade",
  "payment_source": "payment_method",
  "metadata": {
    "reason": "customer_request"
  }
}
```

Behavior:
- upgrades are stored as pending unpaid plan changes and require a successful linked payment before access is upgraded
- downgrades are scheduled in subscription metadata for the period end
- proration is explicitly not implemented in this portfolio phase

### Cancel Subscription

`POST /api/v1/billing/subscriptions/{subscription}/cancel`

Body:

```json
{
  "reason": "customer_request",
  "immediate": false
}
```

Behavior:
- `immediate=false` marks `cancel_at_period_end`
- `immediate=true` moves the subscription to `cancelled` and sets `ended_at`

## Payment Event Integration

Subscription activation is event-driven.

`PaymentSucceeded` triggers `ActivateSubscriptionAfterPaymentSucceeded`.

`PaymentFailed` triggers `MarkSubscriptionPaymentFailed`.

This keeps payment state mutation inside the payment flow and subscription access mutation inside the lifecycle service.

## Renewal Simulation

`SubscriptionLifecycleService::attemptRenewal()` handles due subscriptions:

- free plan: renews without creating a payment
- wallet strategy with enough balance: creates an internal wallet payment and renews immediately after success
- saved payment method with auto-charge consent: creates a simulator payment attempt and waits for payment success
- unavailable wallet/card renewal: records a failed renewal and moves the subscription to `past_due`

Payment creation uses deterministic renewal idempotency keys so repeated scheduler ticks do not create duplicate renewal attempts.

## Scheduler Integration

`billing:check-subscription-expiration` now delegates lifecycle decisions:

- attempts renewal when auto-renew metadata or user payment preferences indicate renewal intent
- expires elapsed subscriptions when no renewal is configured
- leaves `past_due` subscriptions recoverable
- records scheduler and lifecycle activity logs

The scheduler does not call external providers and does not grant access directly.

## Activity Logs

Lifecycle actions use stable activity keys:

- `billing.subscription_created`
- `billing.subscription_activated`
- `billing.subscription_payment_failed`
- `billing.subscription_plan_upgrade_requested`
- `billing.subscription_plan_downgrade_requested`
- `billing.subscription_cancelled`
- `billing.subscription_expired`
- `billing.subscription_renewal_attempted`
- `billing.subscription_renewal_succeeded`
- `billing.subscription_renewal_failed`
- `billing.subscription_past_due`

Activity logging is best-effort and must not break subscription state changes.

## Design Trade-Offs

Plan change details are stored in `subscriptions.metadata.pending_plan_change` for Phase 19.

This avoids schema churn while the product rules for proration, multi-item subscriptions, and enterprise contracts are still intentionally out of scope.

The trade-off is that reporting on pending plan changes is not as query-friendly as a normalized `subscription_plan_changes` table. If this module grows into a production billing product, that table should be introduced before analytics/reporting work.

## Non-Goals

- no real provider integration
- no proration calculation
- no tax/VAT calculation
- no invoice PDF generation
- no frontend billing UI
- no subscription reports API
- no multi-seat subscription items
- no real card storage or tokenization

## Tests

Targeted Phase 19 coverage:

- `SubscriptionLifecycleServiceTest`
- `SubscriptionActivationTest`
- `SubscriptionCancellationTest`
- `SubscriptionRenewalTest`
- `SubscriptionPermissionSeederTest`
- `SubscriptionApiTest`

Regression coverage:

- `PaymentSimulationFlowTest`
- `InvoicePaymentFlowTest`
- `BillingPaymentEventsTest`
- `BillingSubscriptionExpirationCommandTest`

## Related Documentation

- [Plans](./plans.md)
- [Payment API](./payment-api.md)
- [Payment Events](./payment-events.md)
- [Invoices](./invoices.md)
- [Scheduler](./scheduler.md)
- [Statuses](./statuses.md)
- [Idempotency](./idempotency.md)
