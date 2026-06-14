# Billing Reports API

## Purpose

This document describes the implemented backend reporting API for billing analytics.

The reporting layer is backend-authoritative:

- totals are aggregated from the database
- frontend pages must not calculate revenue or lifecycle metrics from partial lists
- read-only reporting is separated from operational admin history

## Common Contract

All endpoints live under `/api/v1/billing/admin/reports`.

Authentication:

- `auth:sanctum`

Permissions:

- `billing.reports.view`
- `billing.reports.view_financials` for money-bearing endpoints

Common filters:

- `date_from`
- `date_to`
- `currency`
- `company_id`
- `seller_id`
- `user_id`
- `plan_id`
- `payment_status`
- `invoice_status`
- `subscription_status`
- `wallet_status`

## Implemented Endpoints

- `GET /api/v1/billing/admin/reports/revenue-summary`
- `GET /api/v1/billing/admin/reports/payment-status-summary`
- `GET /api/v1/billing/admin/reports/revenue-by-plan`
- `GET /api/v1/billing/admin/reports/revenue-by-currency`
- `GET /api/v1/billing/admin/reports/revenue-by-seller-company`
- `GET /api/v1/billing/admin/reports/subscription-metrics`
- `GET /api/v1/billing/admin/reports/invoice-metrics`
- `GET /api/v1/billing/admin/reports/wallet-metrics`

## Response Shape

Every response uses the standard API envelope and returns report metadata in `data`:

```json
{
  "success": true,
  "message": "Revenue summary fetched successfully.",
  "data": {
    "scope": "revenue_summary",
    "generated_at": "2026-06-13T12:00:00Z",
    "filters": {
      "date_from": "2026-06-01",
      "date_to": "2026-06-30"
    },
    "summary": {
      "payment_count": 12,
      "successful_payment_count": 10,
      "revenue_amount": 125000
    },
    "currency_breakdown": []
  }
}
```

## Report Notes

- revenue endpoints only count successful payments as revenue
- payment status summary is intended for operational visibility, not revenue math
- invoice and wallet totals remain grouped and auditable in backend query results
- subscription metrics are lifecycle counts, not billing revenue
- export remains future work and should be implemented as an extensible export layer with pluggable formats, not as a CSV-only shortcut

## Validation

Targeted validation for the reporting layer:

```bash
docker compose exec -T backend php artisan test --filter=BillingReportsApiTest
docker compose exec -T backend php artisan test --filter=BillingReportsPermissionTest
docker compose exec -T backend php artisan test --filter=BillingRbacSeederTest
```
