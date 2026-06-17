# Billing Seeders

## Purpose

This document describes the billing seed data introduced in Phase 7.1.

## BillingSeeder

`BillingSeeder` seeds default billing plans and their feature policies.

Seeded plan slugs:
- `free`
- `basic`
- `pro`
- `enterprise`
- `demo_enterprise`

The seeder also seeds default plan features using stable feature keys from:
- [Billing Plans & Feature Access Design](./plans.md)
- [Enums & Statuses Planning](./statuses.md)

## Idempotency

`BillingSeeder` is idempotent:
- plans are upserted by `slug`
- plan features are upserted by (`plan_id`, `feature_key`, `period`)

Running it multiple times does not create duplicates.

## Scope Boundaries

`BillingSeeder` does:
- create/update default plans
- create/update default plan features

`BillingSeeder` does not:
- implement payment flow
- activate subscriptions
- execute billing runtime logic

## Integration

`BillingSeeder` is registered in `DatabaseSeeder`, so it runs with normal `db:seed` flows.

You can also run it directly:
- `php artisan db:seed --class=BillingSeeder`

## BillingDemoSeeder

`BillingDemoSeeder` seeds an opt-in review dataset for the admin/operator billing surface.

It is controlled by `BILLING_DEMO_SEED=true` in the local environment and remains disabled by default everywhere else.

`DatabaseSeeder` only auto-runs the demo dataset when `app()->environment('local')` and `BILLING_DEMO_SEED=true`.

`php artisan db:seed` always runs the baseline seeders first. The demo dataset is an additional local-only layer and must never replace the baseline bootstrap path.

The demo dataset is split into modular seeders under `backend/database/seeders/billing/`:

- `BillingDemoUserSeeder`
- `BillingDemoPlanSeeder`
- `BillingDemoProviderAccountSeeder`
- `BillingDemoWalletSeeder`
- `BillingDemoSubscriptionSeeder`
- `BillingDemoInvoiceSeeder`
- `BillingDemoPaymentSeeder`
- `BillingDemoWebhookSeeder`
- `BillingDemoRestrictionSeeder`
- `BillingDemoFeatureOverrideSeeder`
- `BillingDemoReportDataSeeder`

Seeded demo data includes:

- demo admin, operator, and normal users
- demo company owner and seller owner users
- demo customers `demo-customer-01@example.com`, `demo-customer-02@example.com`, and `demo-customer-03@example.com`
- demo payments in multiple statuses
- demo payment transactions
- demo idempotency records
- demo subscriptions in multiple statuses
- demo wallets, balances, and wallet transactions
- demo invoices in multiple statuses
- demo webhook deliveries
- demo provider accounts
- demo billing restrictions / blacklist entries
- demo feature overrides
- report-friendly historical rows for dashboard aggregates

The demo seeder is idempotent and safe to rerun. It exists for portfolio walkthroughs and review screens, not for real provider integration or production billing automation.

Recommended local seed flow:

- `APP_ENV=local BILLING_DEMO_SEED=true php artisan db:seed`

Direct seeding the wrapper class still works after the prerequisite billing seeders have run, but the local-only gate is the preferred path for regular development.
