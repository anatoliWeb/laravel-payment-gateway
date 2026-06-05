# Invoice Flow

## Purpose

Invoices provide financial context for payment attempts. They group line items, ownership scope, totals, lifecycle state, and the latest linked payment attempt without performing real provider charges or subscription activation.

## Non-Goals

- No PDF invoice generation.
- No real tax engine.
- No real provider integration.
- No subscription renewal or activation.
- No reporting UI/API.

## Invoice Lifecycle

Statuses:
- `draft`
- `issued`
- `payment_pending`
- `paid`
- `failed`
- `void`
- `overdue`
- `cancelled`

Allowed transitions:
- `draft -> issued`
- `issued -> payment_pending`
- `issued -> void`
- `payment_pending -> paid`
- `payment_pending -> failed`
- `payment_pending -> overdue`
- `failed -> payment_pending` for retry payment creation
- `overdue -> payment_pending` for retry payment creation

Final statuses:
- `paid`
- `void`
- `cancelled`

## Invoice Items

Invoice items are persisted in `invoice_items` and belong to one invoice. Each line uses integer minor-unit amounts only:
- `quantity`
- `unit_amount`
- `subtotal_amount`
- `discount_amount`
- `tax_amount`
- `total_amount`

## Amount Rules

Amounts are never stored as floats. `InvoiceService::recalculateTotals()` derives invoice totals from line items:

`total_amount = subtotal_amount - discount_amount + tax_amount`

`due_amount = total_amount - paid_amount`

## Ownership Scope

Invoices store:
- `payer_user_id`
- `company_id`
- `seller_id`
- `ownership_metadata`

Seller scope infers company scope. A conflicting `company_id` and `seller.company_id` is rejected.

## Payment Linking

`InvoiceService::createPaymentForInvoice()` creates a payment for the invoice `due_amount`, forces currency to match the invoice, links the payment to the invoice, and marks the invoice `payment_pending`.

The invoice is not marked `paid` by payment creation alone. Paid transition requires an explicit later call to `markPaid()` after a safe payment-success signal.

## Payment Status Relationship

Payments remain separate attempts with their own lifecycle. Invoice status summarizes invoice-level settlement state and keeps only the latest payment attempt in `payment_id`.

## Subscription Relationship

Invoices may reference `subscription_id`, but Phase 17 does not activate, renew, cancel, or change subscriptions. Subscription activation remains Phase 19.

## Permissions

Added permissions:
- `billing.invoices.view`
- `billing.invoices.create`
- `billing.invoices.manage`
- `billing.invoices.pay`
- `billing.invoices.view_company`
- `billing.invoices.view_seller`
- `billing.invoices.manage_company`
- `billing.invoices.manage_seller`

Admin receives all invoice permissions through `BillingPermissionSeeder`. Normal users do not receive invoice management permissions by default.

## Activity Logging

Invoice service logs:
- `billing.invoice_created`
- `billing.invoice_issued`
- `billing.invoice_payment_pending`
- `billing.invoice_paid`
- `billing.invoice_failed`
- `billing.invoice_voided`

Metadata includes safe identifiers, status, amounts, currency, payer, company, seller, and payment ID when relevant.

## API Endpoints

- `GET /api/v1/billing/invoices`
- `POST /api/v1/billing/invoices`
- `GET /api/v1/billing/invoices/{invoice}`
- `POST /api/v1/billing/invoices/{invoice}/issue`
- `POST /api/v1/billing/invoices/{invoice}/void`
- `POST /api/v1/billing/invoices/{invoice}/pay`

## Idempotency Notes

Draft invoice creation supports `Idempotency-Key` through `InvoiceService` when passed by API context. Payment creation from invoice reuses existing payment idempotency in `PaymentService`.

Raw idempotency keys are not stored in invoice metadata or activity logs.

## Reporting Readiness

Company/seller ownership fields are stored now so future reporting can filter invoices without backfilling core financial rows.

## Testing Strategy

Targeted tests cover model relations, service transitions, payment linking, permissions, API contract, and selected payment regression flows.

## Status

Phase 17 implements invoice foundation, invoice items, lifecycle transitions, payment linking, permissions, API resources/requests, activity logs, tests, and documentation.
