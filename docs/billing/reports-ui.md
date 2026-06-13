# Billing Reports & Analytics UI

## Purpose

This document describes the Angular reports dashboard for `/admin/billing/reports`.

The screen is backend-authoritative. It consumes report aggregates from `GET /api/v1/billing/admin/reports/*` and does not derive revenue totals from paginated operational lists.

## Route

- `/admin/billing/reports`

The route is protected by `billing.reports.view`.
Financial totals additionally require `billing.reports.view_financials`.

## Sections Implemented

### Filters

- date range
- currency
- company id
- seller id
- user id
- plan id
- payment status
- invoice status
- subscription status
- wallet status

### Summary Cards

- successful revenue
- successful payments
- failed payments
- pending payments
- active subscriptions
- past due subscriptions
- paid invoices
- unpaid / pending invoices
- wallet top-ups
- wallet debits

### Revenue Breakdown

- revenue by plan
- revenue by currency
- revenue by seller/company

### Operational Metrics

- payment status summary
- subscription metrics
- invoice metrics
- wallet metrics

### Notes

- MRR / ARR stays intentionally unavailable until the backend can expose authoritative interval pricing.
- CSV export stays disabled until a dedicated backend export endpoint exists.
- backend report aggregates remain the source of truth.

## Permission Model

- `billing.reports.view` controls access to the report route
- `billing.reports.view_financials` unlocks revenue-bearing totals

Backend permissions remain authoritative; the UI only mirrors access for UX and reduces dead-end navigation.

## Relation To Other Billing Phases

- Phase 22.3: admin / operator billing management UI
- Phase 22.6.1: billing reports backend API

Reports are intentionally separated from operational admin history so the portfolio shows a clean distinction between safe admin review and financial analytics.
