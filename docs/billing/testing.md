# Billing Testing and Validation

## Purpose

This document captures the safe, targeted validation workflow for the billing module.

It focuses on the dedicated testing database and on small test slices instead of the full suite.

## Testing Database Rule

Use the testing database only:

- `.env.testing`
- `phpunit.xml`
- `tests/TestCase.php`

The testing database should resolve to `payment_gateway_testing`.

Never run `db:wipe` or `migrate:fresh` against the default development database when validating billing behavior.

## Safe Reset Commands

If the testing database needs to be repaired:

```bash
php artisan db:wipe --env=testing --force
php artisan migrate:fresh --env=testing --force
```

Seed only if the targeted test slice requires data.

## Targeted Billing Checks

Use focused test runs for billing changes:

```bash
php artisan test --filter=BillingApiResponseContractTest
php artisan test --filter=BillingDomainExceptionTest
php artisan test --filter=OpenApiResponseEnvelopeTest
php artisan test --filter=OpenApiValidationErrorFormatTest
```

Recommended billing-specific slices:

- payment creation and simulation
- subscription activation and cancellation
- invoice issue/pay/void flows
- wallet top-up and wallet adjustment flows
- idempotency replay/conflict behavior
- webhook retry behavior
- activity log coverage

## What To Avoid

- full suite runs unless explicitly needed
- database resets without `--env=testing`
- real provider calls
- queue or scheduler side effects outside targeted tests

## Why This Matters

Billing tests are sensitive to shared state and replay behavior.

The simulator module relies on deterministic IDs, stable error codes, and isolated database state so that the same API request produces the same result during replay.

## Validation Checklist

- confirm `payment_gateway_testing`
- reset only the testing database when needed
- run focused billing tests
- confirm OpenAPI smoke tests if the response envelope changes
- keep production and development databases untouched

## Status

This document is documentation only and does not change runtime behavior.
