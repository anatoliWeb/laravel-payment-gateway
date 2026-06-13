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

It is controlled by `BILLING_DEMO_SEED=true` in non-production environments and remains disabled by default.

Seeded demo data includes:

- demo admin, operator, and normal users
- demo payments in multiple statuses
- demo payment transactions
- demo subscriptions in multiple statuses
- demo wallets and wallet transactions
- demo invoices in multiple statuses
- demo webhook deliveries
- demo provider accounts
- demo billing restrictions / blacklist entries
- demo feature overrides

The demo seeder is idempotent and safe to rerun. It exists for portfolio walkthroughs and review screens, not for real provider integration or production billing automation.
