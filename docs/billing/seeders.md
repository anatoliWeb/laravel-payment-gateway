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

