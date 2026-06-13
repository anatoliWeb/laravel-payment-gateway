# Billing Demo Flows

## Purpose

This document is the reviewer-facing guide for the Phase 22.5 billing demo layer.

It does not invent backend data or payment logic. It simply organizes the existing Angular billing screens into a guided walkthrough so the portfolio can be reviewed without Postman.

## Route

- Frontend route: `/billing/demo`

## Recommended Walkthrough Order

1. Free plan limits
2. Paid plan purchase
3. Wallet top-up
4. Wallet payment
5. Payment method payment
6. Wallet-first fallback
7. Invoice payment
8. Subscription activation
9. Failed payment
10. Webhook delivery history
11. Billing restriction / blacklist
12. Feature override
13. Seller/company scoped payment

## Flows

### Free plan limits

- Status: `partial`
- UI route: `/billing`
- Related UI: user billing portal
- Steps:
  - open the billing portal
  - review current plan and placeholder usage sections
  - use related chat UI for a limit-triggering action if the seeded environment supports it
  - observe the stable limit error and activity trail where available
- Notes:
  - the billing page itself does not trigger chat limits
  - this is a portfolio guide, not a fake usage simulation

### Paid plan purchase

- Status: `available`
- UI route: `/billing/checkout`
- Related UI: checkout/payment screen
- Steps:
  - open checkout
  - choose a plan
  - pick payment source and strategy
  - submit once
  - observe the payment status
- Notes:
  - client-side idempotency protects the submit action

### Wallet top-up

- Status: `available`
- UI route: `/billing/wallet/top-up`
- Related UI: wallet top-up screen
- Steps:
  - open wallet top-up
  - enter amount and currency
  - choose a saved simulator payment method
  - submit
  - observe the payment and wallet transaction result

### Wallet payment

- Status: `available`
- UI route: `/billing/checkout`
- Related UI: checkout/payment screen
- Steps:
  - open checkout
  - choose wallet as payment source
  - submit
  - observe the payment response

### Payment method payment

- Status: `available`
- UI route: `/billing/checkout`
- Related UI: checkout/payment screen
- Steps:
  - open checkout
  - choose payment method as source
  - select a saved simulator payment method
  - submit
  - observe the payment response
- Safety:
  - no raw card data is collected

### Wallet-first fallback

- Status: `partial`
- UI route: `/billing/checkout`
- Related UI: checkout/payment screen
- Steps:
  - open checkout
  - choose wallet-first strategy
  - submit
  - observe the final status and server-controlled fallback behavior

### Invoice payment

- Status: `partial`
- UI route: `/billing/invoices/:invoiceId/pay`
- Related UI: invoice payment screen
- Steps:
  - open the invoice payment page for a known invoice id
  - review the invoice summary
  - choose payment source and strategy
  - submit
  - observe the payment response
- Notes:
  - invoice discovery is still user-scoped rather than a dedicated demo list flow

### Subscription activation

- Status: `partial`
- UI route: `/billing/checkout`
- Related docs: [Subscription Lifecycle](./subscription-lifecycle.md)
- Steps:
  - create or reuse a pending subscription scenario
  - pay the linked payment
  - observe activation after a successful payment
  - verify a failed payment does not activate it

### Failed payment

- Status: `admin-only`
- UI route: `/admin/billing`
- Related docs: [Admin / Operator Billing Management UI](./admin-operator-ui.md)
- Steps:
  - open the admin billing dashboard
  - review the payment detail and transaction history for a failed payment
  - use a safe failure path only if the current environment already exposes one
- Notes:
  - the user-facing UI does not expose hidden simulation controls
  - admin review is read-only; simulation remains permission/demo-gated elsewhere

### Webhook delivery history

- Status: `available`
- UI route: `/admin/billing`
- Related docs: [Webhooks](./webhooks.md)
- Steps:
  - open the admin billing dashboard
  - enter a payment id
  - review webhook delivery rows
  - retry failed rows when permissions allow

### Billing restriction / blacklist

- Status: `admin-only`
- UI route: `/admin/billing`
- Related docs: [Admin / Operator Billing Management UI](./admin-operator-ui.md)
- Steps:
  - review the read-only restriction list/detail data
  - note that restriction CRUD is intentionally not exposed yet
  - treat this as a roadmap note, not a live flow

### Feature override

- Status: `admin-only`
- UI route: `/admin/billing`
- Related docs: [Overrides](./overrides.md)
- Steps:
  - review the read-only feature override list/detail data
  - note that feature override CRUD remains a backend gap
  - keep the demo guide honest about that gap

### Seller/company scoped payment

- Status: `partial`
- UI routes: `/billing/company`, `/billing/seller`, `/billing/checkout`
- Related docs: [Seller / Company Billing Views](./seller-company-ui.md)
- Steps:
  - open company or seller billing view
  - review the ownership context and gap notes
  - use checkout with company or seller ownership context
  - observe that revenue is not calculated client-side

## Safety Notes

- no real payment provider calls
- no raw card data
- no provider secrets
- no raw idempotency keys
- no fake revenue totals

## Screenshots / GIF Notes

This phase is documentation-first.
If visual proof is needed later, add screenshots or short GIF notes near the related billing docs or README section.

## Relation to Other Billing UI

- User portal: [User Portal UI](./user-portal-ui.md)
- Checkout/payment UI: [Checkout / Payment UI](./checkout-payment-ui.md)
- Admin/operator UI: [Admin / Operator Billing Management UI](./admin-operator-ui.md)
- Seller/company views: [Seller / Company Billing Views](./seller-company-ui.md)

## Status

The guide is implemented as an Angular front-end walkthrough for portfolio review.
