# Billing RBAC Permissions

## Purpose

Billing RBAC permissions define administrative access boundaries for the billing, payment simulator, overrides, wallet, currency, webhook, and reporting areas.

They are seeded before billing/payment API endpoints are exposed so future controllers can use stable permission keys instead of inventing authorization names per endpoint.

## Permission Groups

Billing plans:
- `billing.plans.view`
- `billing.plans.manage`

Subscriptions:
- `billing.subscriptions.view`
- `billing.subscriptions.manage`

Usage:
- `billing.usage.view`
- `billing.usage.manage`

Overrides and restrictions:
- `billing.overrides.view`
- `billing.overrides.manage`
- `billing.restrictions.view`
- `billing.restrictions.manage`

Payments:
- `billing.payments.view`
- `billing.payments.create`
- `billing.payments.simulate`
- `billing.payments.refund`

Webhooks:
- `billing.webhooks.view`
- `billing.webhooks.retry`

Wallets:
- `billing.wallets.view`
- `billing.wallets.manage`

Currencies:
- `billing.currencies.view`
- `billing.currencies.manage`

Reports:
- `billing.reports.view`

## Admin Permissions

The `admin` role receives all seeded billing permissions.

The seeder uses idempotent permission creation and `syncWithoutDetaching` for role assignment, so repeated seed runs do not create duplicate permissions and do not remove existing admin permissions.

## Normal User Access Model

Normal users do not receive global billing administration permissions by default.

Customer-facing billing access should be implemented through ownership checks, for example:
- users can view their own subscription
- users can view their own payment history
- users can view their own usage records

Those ownership checks belong to future billing API/services work, not to the RBAC seeder.

## Ownership Checks vs Global Permissions

Global permissions are for administrative operations.

Ownership checks are for user-specific billing data. A regular user should not need `billing.payments.view` to see their own payments through a customer endpoint. That endpoint should verify ownership or tenancy instead.

## Simulator Permissions

Payment simulator operations must be separated from normal payment creation.

Expected future permission usage:
- `billing.payments.create` for payment creation operations.
- `billing.payments.simulate` for success/failure simulation endpoints.
- `billing.payments.refund` for simulated refund actions if implemented.

## Wallet and Currency Permissions

Wallet and currency permissions are seeded ahead of roadmap phases 12.1, 12.2, and 13.2.

They do not implement wallet, currency, or autopay logic. They only reserve stable authorization keys for future work.

## Future API Usage

Expected future endpoint guards:
- plan management endpoints require `billing.plans.manage`
- subscription management endpoints require `billing.subscriptions.manage`
- usage administration endpoints require `billing.usage.manage`
- override and restriction endpoints require `billing.overrides.manage` or `billing.restrictions.manage`
- payment simulation endpoints require `billing.payments.simulate`
- webhook retry endpoints require `billing.webhooks.retry`
- wallet administration endpoints require `billing.wallets.manage`
- currency administration endpoints require `billing.currencies.manage`
- billing reports require `billing.reports.view`

## Non-Goals

Phase 10.2 does not create:
- controllers
- routes
- FormRequests
- resources
- jobs
- payment creation flow
- paid chat integration
- wallet/currency/autopay runtime logic
- frontend changes

## Status

Implemented in `BillingPermissionSeeder`.

The billing permissions are assigned to the `admin` role. The default `user` role does not receive billing admin permissions.
