# Phase 25.0 Runtime Smoke Check & Screenshot Plan

This file is a docs-only capture plan for the portfolio-ready billing module.
It does not define product behavior. It documents what should be verified in runtime and what screenshots should be created for the README/portfolio story.

## Vue Admin Billing Launchpad

The Vue Admin `/billing` route is a permission-aware launchpad, not a duplicated billing management UI.
It should route admins to the real Angular surfaces instead of rendering placeholder financial data.

- `docs/assets/screenshots/billing/00-vue-admin-billing-launchpad.png`

## Runtime Smoke Checklist

- [ ] Verify app boots from Docker
- [ ] Verify backend billing routes are registered
- [ ] Verify frontend build passes
- [ ] Verify demo seed command and demo users
- [ ] Verify user billing portal route
- [ ] Verify checkout/payment route
- [ ] Verify wallet top-up route
- [ ] Verify invoice payment route
- [ ] Verify admin billing route
- [ ] Verify admin reports route
- [ ] Verify demo flows route
- [ ] Verify company billing route
- [ ] Verify seller billing route
- [ ] Verify Vue admin billing launchpad route
- [ ] Create screenshot folder structure
- [ ] Add screenshot naming plan
- [ ] Add diagram naming plan
- [ ] Document manual smoke checklist

## Demo Seed Notes

- Seed command: `docker compose exec -T backend sh -lc 'APP_ENV=local BILLING_DEMO_SEED=true php artisan db:seed'`
- Demo users:
  - admin: `demo-admin@example.com`
  - operator: `demo-operator@example.com`
  - normal user: `demo-normal@example.com`
  - company owner: `demo-company-owner@example.com`
  - seller owner: `demo-seller-owner@example.com`
  - customers: `demo-customer@example.com`, `demo-customer-01@example.com`, `demo-customer-02@example.com`, `demo-customer-03@example.com`
- Roles/permissions:
  - admin gets full billing management permissions
  - operator gets read-only billing review permissions
  - normal user remains customer-scoped and must not access admin billing surfaces
- Demo data is local-only and intentionally richer than a single happy-path payment so the reports and admin history screens have realistic state transitions.

## Manual Smoke Checklist

Verify the following routes in a browser after the Docker stack is running:

| Route | Role | Expected Result | Screenshot Path | Status |
|---|---|---|---|---|
| `/billing` | customer | user billing portal renders subscription, wallet, methods, and payment history | `docs/assets/screenshots/billing/01-user-billing-portal.png` | planned |
| checkout route | customer | checkout summary, source, amount, and idempotent submit flow render | `docs/assets/screenshots/billing/02-checkout-plan-purchase.png` | planned |
| wallet top-up route | customer | top-up form and pending/success state render | `docs/assets/screenshots/billing/03-wallet-top-up.png` | planned |
| invoice payment route | customer | invoice payment summary and payment state render | `docs/assets/screenshots/billing/04-invoice-payment.png` | planned |
| `/admin/billing` | admin/operator | operational billing management surfaces render | `docs/assets/screenshots/billing/05-admin-billing-dashboard.png` | planned |
| admin payment detail section | admin/operator | payment detail and transaction history render | `docs/assets/screenshots/billing/06-admin-payment-detail-transactions.png` | planned |
| `/admin/billing/reports` | admin | reports dashboard renders aggregates and filters | `docs/assets/screenshots/billing/07-admin-reports-dashboard.png` | planned |
| `/billing/demo` | customer/admin/operator | demo flow cards and walkthrough notes render | `docs/assets/screenshots/billing/08-billing-demo-flows.png` | planned |
| `/billing/company` | `demo-company-owner@example.com` | gap-aware company billing ownership shell renders without fake scoped data | `docs/assets/screenshots/billing/09-company-billing-view.png` | planned |
| `/billing/seller` | `demo-seller-owner@example.com` | gap-aware seller billing ownership shell renders without fake scoped data | `docs/assets/screenshots/billing/10-seller-billing-view.png` | planned |
| Vue admin `/billing` | admin/operator | billing launchpad routes to the Angular operational and reports surfaces | `docs/assets/screenshots/billing/00-vue-admin-billing-launchpad.png` | planned |

## Screenshot Naming Plan

The following file naming convention keeps the portfolio capture set stable and easy to reference:

1. `01-user-billing-portal.png`
2. `02-checkout-plan-purchase.png`
3. `03-wallet-top-up.png`
4. `04-invoice-payment.png`
5. `05-admin-billing-dashboard.png`
6. `06-admin-payment-detail-transactions.png`
7. `07-admin-reports-dashboard.png`
8. `08-billing-demo-flows.png`
9. `09-company-billing-view.png`
10. `10-seller-billing-view.png`

## Diagram Naming Plan

Create draft diagrams as Mermaid files under `docs/assets/diagrams/`:

- `billing-architecture.mmd`
- `billing-flow.mmd`
- `webhook-retry-flow.mmd`

## Diagram Notes

- `billing-architecture.mmd` should show Laravel API, Angular UI, MySQL, Redis, queue worker, scheduler, Reverb, and the payment gateway simulator.
- `billing-flow.mmd` should show user action, idempotency key, payment creation, simulator result, and resulting subscription/invoice/wallet updates.
- `webhook-retry-flow.mmd` should show event creation, queued delivery, retry/backoff, and manual retry review.

## Source Links

- [Billing overview](../billing/overview.md)
- [Billing seeders](../billing/seeders.md)
- [Billing scheduler](../billing/scheduler.md)
- [Queue operations](../devops/queues.md)
- [Scheduler operations](../devops/scheduler.md)
