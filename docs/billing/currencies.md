# Currency & Exchange Rates Foundation

## Purpose

Currency and exchange rate support is the foundation for future wallet balances, multi-currency payments, refunds, and simulated payment preferences.

Phase 12.1 creates the catalog and manual/simulated rate layer only. Runtime wallet balances and payment integration come later.

## Non-Goals

This phase does not implement:
- wallet models
- wallet balances
- wallet transactions
- auto top-up
- payment creation flow
- subscription/payment currency conversion
- external exchange-rate provider calls
- controllers, routes, FormRequests, resources, or jobs
- frontend changes

## Currency Catalog

The `currencies` table stores stable ISO-like 3-letter currency codes.

Important fields:
- `code`: uppercase API/seeder key such as `USD`
- `name`: human-readable display name
- `symbol`: optional display symbol
- `decimal_precision`: minor-unit precision
- `is_active`: whether the currency is available for future billing/wallet flows
- `is_base`: system base currency marker
- `description`: operational note
- `metadata`: safe extension payload without secrets

## Base Currency

USD is the seeded base currency.

This choice fits the SaaS/payment simulator portfolio context and keeps future international billing examples simple. UAH, EUR, PLN, and GBP remain active supported currencies.

Only one seeded base currency is active after `CurrencySeeder` runs.

## Exchange Rates

The `exchange_rates` table stores manual or simulated conversion rates.

Important fields:
- `base_currency_id`: currency being converted from
- `quote_currency_id`: currency being converted to
- `rate`: decimal conversion rate stored as `decimal(20, 8)`
- `source`: source identifier such as `manual` or `simulated`
- `valid_from`: start of validity window
- `valid_until`: optional end of validity window
- `is_active`: whether the rate can be used for active lookup
- `metadata`: safe audit/diagnostic payload

The schema intentionally does not enforce a hard unique constraint on currency pairs, so historical rates can exist later.

## Manual / Simulated Rates

This project does not call real exchange-rate providers.

Seeded rates are manual/simulated current rates from USD to:
- EUR
- UAH
- PLN
- GBP

Future production integrations would need a provider abstraction, reconciliation, source timestamps, and operational monitoring. That is outside this portfolio simulator phase.

## Precision and Rounding

Amounts are represented in minor units.

Conversion uses:
- source currency decimal precision
- target currency decimal precision
- active direct exchange rate
- deterministic half-up rounding to the target minor unit

The conversion service avoids float arithmetic by scaling the stored decimal rate to an integer.

## Seeded Currencies

Seeded active currencies:
- USD: base currency
- EUR
- UAH
- PLN
- GBP

The seeder is idempotent and can be safely rerun.

## Wallet Readiness

Wallet balances in Phase 12.2 can reference `currencies` for one balance per currency.

This phase does not create wallet tables or wallet transaction logic.

## Payment Readiness

Payments can continue storing currency codes as strings until payment flow integration is implemented.

Future payment phases can use the currency catalog to validate supported currencies, display precision, and conversion rules.

## Testing Strategy

Tests cover:
- seeded currencies and active base currency
- seeder idempotency
- model casts, scopes, and relations
- exchange rate casts and validity fields
- active manual rate lookup
- same-currency conversion
- missing-rate safe result
- deterministic rounding

## Status

Phase 12.1 implements the currency and exchange-rate foundation.

Wallet balances, multi-currency payment flow, and auto top-up are intentionally not implemented yet.
