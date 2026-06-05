# Company / Seller Ownership Scope

## Purpose

Phase 14.1 adds an ownership foundation for billing data that must later support company reports, seller-specific provider accounts, webhook routing, and multi-merchant flows.

This is an additive foundation, not a complete marketplace or tenant-isolation implementation.

## Non-Goals

This phase does not implement:
- company or seller management APIs
- report APIs or UI
- subscription ownership migration
- company/seller wallets
- webhook delivery or routing
- real provider integrations
- automatic migration of existing users into sellers

## Hierarchy

```text
Company (optional)
  -> Seller / Merchant
       -> Customer / End User
```

A seller may exist without a company. A payment may exist without both company and seller and remain user-scoped.

## Company

`companies` is the top-level optional ownership and reporting scope.

It has sellers, explicit `company_users` memberships, payments, and provider accounts. Company status must be `active` before it can be assigned to a new payment.

## Seller / Merchant

`sellers` represents a merchant scope.

Each seller has an `owner_user_id` and may belong to a company. Seller status must be `active` before it can be assigned to a new payment. When a seller belongs to a company, payment ownership infers that company.

## Customer / End User

`seller_customers` links existing platform users to sellers.

The link is available for future seller-specific customer rules. Payment creation does not require this relation by default because existing simple user flows and merchant payment flows must remain possible. Services may explicitly request customer-link enforcement later.

## Payment Ownership

Payments keep the existing `user_id` for backward compatibility and add:
- `payer_user_id`
- nullable `company_id`
- nullable `seller_id`
- nullable `provider_account_id`
- nullable `ownership_metadata`

`OwnershipScopeService` resolves active scopes, infers company from seller, and rejects company/seller mismatches with `payment_ownership_scope_conflict`.

Existing user-scoped payments remain valid when all new ownership fields are null.

## Invoice Ownership

Invoices use the same additive ownership model as payments:
- `payer_user_id`
- nullable `company_id`
- nullable `seller_id`
- nullable `ownership_metadata`

Seller scope infers company scope, and conflicting seller/company combinations are rejected before invoice creation. Existing user-scoped invoices remain valid without company or seller ownership.

Payment simulation preserves ownership fields during status transitions. See [Payment Simulation Flow](./payment-simulation.md).

## Provider Account Ownership

Provider accounts add nullable `company_id` and `seller_id`.

Resolution priority is:
1. active seller provider account
2. active company provider account
3. active unscoped user provider account
4. enabled platform `.env` configuration
5. simulator default

The existing required `user_id` remains unchanged, so company/seller accounts still have a custodial user. This is intentionally not presented as a complete multi-tenant credential vault.

## Wallet Ownership

Wallets remain user-owned.

Seller/company payment ownership does not move funds into a company or seller wallet and does not change current wallet balance or ledger rules.

## Reporting Scope

Future company reports should filter by `payments.company_id`.

Future seller reports should filter by `payments.seller_id`.

Future customer reports should filter by `payments.payer_user_id`, falling back to legacy `user_id` for pre-foundation records.

No report API or UI is implemented in Phase 14.1.

## RBAC Scope

Global company/seller management, report, payment-scope, and provider-account-scope permissions are seeded for admin.

Normal users receive none by default. Customer-facing access uses ownership checks:
- active company member can access company scope
- seller owner can access seller scope
- active company member can access a seller under that company
- payer can access their own payment

## Idempotency Scope

Payment creation idempotency includes requested `company_id` and `seller_id` in the deterministic payload fingerprint.

This prevents the same user-scoped key from being replayed into a different ownership context. The central idempotency registry remains actor-user scoped.

## Webhook Scope Readiness

Payment ownership and `provider_account_id` provide the routing context future webhook payload/delivery services will need.

Phase 14.1 does not dispatch or deliver webhooks.

## Migration / Backward Compatibility

All ownership migrations are additive:
- no existing payment columns are removed
- no existing provider account columns are removed
- no existing users are converted into sellers
- new payment/provider ownership fields are nullable
- legacy `payments.user_id` and `payment_provider_accounts.user_id` remain in use

## Testing Strategy

Targeted tests cover:
- company, seller, membership, and customer relations
- user/seller/company scope resolution
- mismatch rejection and access checks
- payment ownership persistence
- seller/company/user provider account priority and isolation
- admin-only permission seeding
- existing payment, idempotency, wallet/card, and provider regression

## Demo Seed Data

`CompanySellerSeeder` creates a deterministic local/demo ownership graph:

```text
Demo Company
  -> Demo Seller
       -> Demo Customer
```

The seeder creates dedicated demo company-owner, seller-owner, and customer users only when their stable emails do not already exist. Existing users are reused without changing their passwords or roles.

It also creates one seller-scoped simulator provider account with a fake encrypted credential. No real provider key or secret is stored.

The company, seller, membership, customer relation, users, and simulator account are seeded idempotently. Repeated runs do not create duplicate ownership records and do not convert unrelated existing users into sellers or customers.

## Status

Phase 14.1 implements the minimum Company / Seller ownership foundation.

Full marketplace behavior, report APIs, company/seller wallets, webhook delivery, and real provider integrations remain future work.
