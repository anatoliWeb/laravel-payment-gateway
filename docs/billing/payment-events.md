# Billing Domain Events & Post-Event Actions

## Purpose

Billing domain events create a boundary between financial state transitions and follow-up side effects. Payment, invoice, and wallet services keep ownership of atomic state changes; listeners and jobs own post-event actions such as notifications, receipt generation hooks, seller/company notifications, and future lifecycle automation.

## Non-Goals

- No real SMS provider integration.
- No real email provider integration.
- No PDF or receipt document generation.
- No subscription activation, renewal, expiration, or cancellation.
- No real payment provider integration.
- No duplicate webhook delivery beyond the existing Phase 16 flow.

## Event-Driven Boundary

Core services should not directly send SMS, email, generate documents, or notify sellers. They dispatch safe domain events after successful state changes. Listeners then queue placeholder jobs or intentionally skip work when a feature is future-only.

This keeps financial transactions small, testable, and resilient when later side effects fail or become slow.

## Payment Events

Implemented:
- `PaymentCreated`
- `PaymentSucceeded`
- `PaymentFailed`
- `PaymentExpired`
- `PaymentCancelled`

`PaymentExpired` and `PaymentCancelled` are foundation events for future scheduler/cancellation flows. Current runtime integration dispatches created, succeeded, and failed events.

## Invoice Events

Implemented:
- `InvoiceIssued`
- `InvoicePaymentPending`
- `InvoicePaid`
- `InvoiceFailed`

## Wallet Events

Implemented:
- `WalletCredited`
- `WalletDebited`

Idempotent wallet replays return existing ledger rows and do not dispatch duplicate wallet events.

## Future Subscription Events

Planned names:
- `SubscriptionActivated`
- `SubscriptionRenewed`
- `SubscriptionExpired`
- `SubscriptionCancelled`

These are documented only. Subscription activation remains Phase 19.

## Post-Event Actions

Listeners:
- `DispatchPaymentNotificationActions`
- `DispatchInvoiceNotificationActions`
- `DispatchReceiptGenerationAction`
- `DispatchBillingWebhookAction`
- `DispatchSellerCompanyNotificationAction`

Placeholder jobs:
- `GenerateBillingReceiptJob`
- `SendBillingSmsNotificationJob`
- `SendBillingEmailNotificationJob`
- `NotifySellerCompanyBillingEventJob`

## Receipt / Document Generation

Receipt/document generation is represented by `GenerateBillingReceiptJob`. The job is currently a no-op placeholder that logs the skipped action. It does not generate PDFs or files.

## SMS / Email Notifications

SMS and email notification jobs are placeholders. They do not call external providers and accept safe event payloads only.

## Webhook Dispatch

Phase 16 already creates and dispatches outbound payment webhooks from `PaymentSimulationService`. `DispatchBillingWebhookAction` is a structural listener for future migration and intentionally does not create webhook deliveries today, preventing duplicate callbacks.

## Seller / Company Notifications

Seller/company notifications are queued only when the event payload includes `company_id` or `seller_id`. The placeholder job does not call external systems.

## Idempotency and Duplicate Prevention

Current protections:
- payment creation replay does not dispatch `PaymentCreated` twice
- repeated final simulation no-op does not dispatch duplicate success/failure events
- invoice payment pending replay does not dispatch `InvoicePaymentPending` twice
- wallet idempotency replay does not dispatch duplicate wallet events

## Security and Payload Safety

Event payloads include stable identifiers, amounts, currency, status, payer, company, seller, subscription, invoice, and payment references. They do not include raw idempotency keys, provider secrets, raw card data, or unsafe metadata.

## Testing Strategy

Targeted tests cover payment events, invoice events, wallet events, placeholder queued jobs, webhook duplicate prevention, and payload safety.

## Status

Phase 17.1 adds the event-driven foundation and placeholder post-event action layer. Real providers, PDF generation, and subscription lifecycle actions remain future work.
