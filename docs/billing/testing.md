# Billing Testing And Validation

## Purpose

This document captures the safe validation workflow for the billing module.
It is intentionally narrow: run targeted slices, keep the testing database isolated, and avoid full-suite churn unless a schema issue forces it.

## Database Strategy

Use the testing database only:

- `.env.testing`
- `phpunit.xml`
- `tests/TestCase.php`

The testing database should resolve to `payment_gateway_testing`.

Never run `db:wipe` or `migrate:fresh` against the default development database when validating billing behavior.

### Safe Reset Commands

If the testing database needs repair:

```bash
php artisan db:wipe --env=testing --force
php artisan migrate:fresh --env=testing --force
```

Seed only if the targeted slice needs it. Keep the default development database untouched.

### When To Use `DatabaseTransactions`

Prefer `DatabaseTransactions` for large billing feature tests when the schema is already migrated.

Use it for:

- API flows that create and inspect many related rows
- idempotency replay checks
- webhook retry checks
- provider configuration and credential isolation checks
- admin read-surface tests with seeded data

### When To Use `RefreshDatabase`

Use `RefreshDatabase` when the test is specifically validating:

- migration shape
- schema bootstrapping
- database comments / DDL changes
- a tiny isolated unit of persistence that does not benefit from transaction wrapping

In this project, Docker/MySQL migration reset can be unstable in broader billing groups, so transaction-based tests are the safer default once the schema exists.

## Targeted Backend Checks

Start with focused billing slices:

```bash
docker compose exec -T backend php artisan test --filter=BillingApiResponseContractTest
docker compose exec -T backend php artisan test --filter=BillingDomainExceptionTest
docker compose exec -T backend php artisan test --filter=PaymentCreationFlowTest
docker compose exec -T backend php artisan test --filter=PaymentIdempotencyTest
docker compose exec -T backend php artisan test --filter=PaymentSimulationFlowTest
docker compose exec -T backend php artisan test --filter=WebhookDeliveryFlowTest
docker compose exec -T backend php artisan test --filter=WalletApiTest
docker compose exec -T backend php artisan test --filter=PaymentMethodsApiTest
docker compose exec -T backend php artisan test --filter=PaymentPreferencesApiTest
```

Additional high-value slices:

- subscription creation, cancellation, and renewal
- wallet top-up and wallet adjustment idempotency
- provider factory / config resolver / credential masking
- scheduler command registration and retry commands
- admin read-surface API access control
- demo seed data stability

## Targeted Frontend Checks

Use small component/spec slices:

```bash
docker compose exec -T frontend npm test -- --watch=false --include src/app/features/billing/pages/billing-portal/billing-portal.component.spec.ts
docker compose exec -T frontend npm test -- --watch=false --include src/app/features/billing/pages/billing-checkout/billing-checkout-page.component.spec.ts
docker compose exec -T frontend npm test -- --watch=false --include src/app/features/billing/pages/wallet-top-up/wallet-top-up-page.component.spec.ts
docker compose exec -T frontend npm test -- --watch=false --include src/app/features/admin-billing/pages/admin-billing-dashboard/admin-billing-dashboard-page.component.spec.ts
```

The Angular CLI test runner is the current source of truth for frontend specs.

## Why Docker Matters

Billing tests are sensitive to shared state and replay behavior.

The simulator module relies on deterministic IDs, stable error codes, and isolated database state so that the same API request produces the same result during replay.

## Local Environment Caveat

On Windows hosts, Angular/esbuild can fail with `spawn EPERM` even when the build is otherwise healthy.

If that happens:

- prefer the Docker frontend container for build/test validation
- treat the containerized Angular run as the source of truth
- do not assume the UI code is broken just because the host runner fails

## Validation Checklist

- confirm `payment_gateway_testing`
- reset only the testing database when needed
- run focused billing tests
- confirm OpenAPI smoke tests if the response envelope changes
- keep production and development databases untouched
- use Docker when host Angular spawning is unstable

## Status

This document is documentation only and does not change runtime behavior.
