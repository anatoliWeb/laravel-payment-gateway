# TODO — Billing & Payment Gateway Module

## Project Direction

We are not creating a standalone payment gateway.

We are extending the existing Laravel SaaS foundation with a Billing & Payment module.

The goal is to demonstrate Senior Backend Developer skills through:

- SaaS billing architecture
- paid plans
- paid chat features
- future paid dialer/calling features
- payment gateway simulator
- payment transactions
- idempotency
- webhook callbacks
- queues
- cron/scheduled jobs
- activity logging
- API-first design
- Service Layer
- DTO
- FormRequest validation
- tests
- documentation

---

## Phase 0 — Safety & Baseline Verification

- [x] Confirm correct project root
- [x] Confirm git repository root
- [x] Check current branch
- [x] Create backup branch before changes
- [x] Run `git status`
- [x] Ensure no important uncommitted work will be overwritten
- [x] Check Docker stack status
- [x] Check backend container status
- [x] Check MySQL container status
- [x] Check Redis container status
- [x] Check queue-worker container status
- [x] Check current routes
- [x] Check current migrations
- [x] Check current tests
- [x] Save current audit report in docs if needed

Phase 0 completed after Docker, DB, migrations, routes, Redis, queue worker, Reverb, and baseline tests were verified. Billing/payment logic is not implemented yet.

---

## Phase 1 — Product Billing Strategy

- [x] Define billing module purpose
- [x] Define what is free
- [x] Define what is paid
- [x] Define what belongs to chat billing
- [x] Define what will be reusable for future dialer billing
- [x] Define plan types
- [x] Define usage-based limits
- [x] Define subscription lifecycle
- [x] Define payment lifecycle
- [x] Define invoice lifecycle
- [x] Define webhook lifecycle
- [x] Define cron/scheduler responsibilities
- [x] Document billing strategy in `docs/billing/overview.md`

---

## Phase 2 — Plans & Feature Access Design

- [x] Design `plans` table
- [x] Design `plan_features` table
- [x] Design `subscriptions` table
- [x] Design `subscription_items` table if needed
- [x] Design `feature_usages` table
- [x] Define plan slugs
- [x] Define default free plan
- [x] Define paid basic plan
- [x] Define paid pro plan
- [x] Define enterprise/demo plan if needed
- [x] Define chat feature limits
- [x] Define future dialer feature limits
- [x] Define storage/retention limits if useful
- [x] Define plan upgrade rules
- [x] Define plan downgrade rules
- [x] Define subscription cancellation rules
- [x] Document plans in `docs/billing/plans.md`

---

## Phase 3 — Payment Gateway Simulator Design

- [x] Design payment gateway simulator concept
- [x] Define supported fake payment methods
- [x] Define payment creation flow
- [x] Define payment success simulation flow
- [x] Define payment failure simulation flow
- [x] Define payment expiration flow
- [x] Define payment retry flow
- [x] Define webhook callback flow
- [x] Define idempotency behavior
- [x] Define transaction history behavior
- [x] Define payment metadata structure
- [x] Define simulator API contract
- [x] Document simulator in `docs/billing/payment-gateway-simulator.md`

---

## Phase 4 — Database Schema Planning

- [x] Plan `plans` migration
- [x] Plan `plan_features` migration
- [x] Plan `subscriptions` migration
- [x] Plan `feature_usages` migration
- [x] Plan `payments` migration
- [x] Plan `payment_transactions` migration
- [x] Plan `idempotency_keys` migration
- [x] Plan `webhook_deliveries` migration
- [x] Plan relation between users and subscriptions
- [x] Plan relation between subscriptions and payments
- [x] Plan relation between payments and transactions
- [x] Plan relation between payments and webhook deliveries
- [x] Plan indexes
- [x] Plan unique constraints
- [x] Plan foreign keys
- [x] Plan JSON fields
- [x] Plan enum/status columns
- [x] Document DB schema in `docs/billing/database.md`

---

## Phase 5 — Billing Domain Structure

- [x] Create target folder plan for `app/Services/Billing`
- [x] Create target folder plan for `app/Services/Payments`
- [x] Create target folder plan for `app/DTO/Billing`
- [x] Create target folder plan for `app/DTO/Payments`
- [x] Create target folder plan for `app/Enums/Billing`
- [x] Create target folder plan for `app/Enums/Payments`
- [x] Create target folder plan for `app/Http/Requests/Api/V1/Billing`
- [x] Create target folder plan for `app/Http/Requests/Api/V1/Payments`
- [x] Create target folder plan for `app/Http/Resources/Billing`
- [x] Create target folder plan for `app/Http/Resources/Payments`
- [x] Create target folder plan for `app/Jobs/Billing`
- [x] Create target folder plan for `app/Jobs/Payments`
- [x] Create target folder plan for `app/Exceptions/Billing`
- [x] Create target folder plan for `app/Exceptions/Payments`
- [x] Document folder structure in `docs/billing/architecture.md`

---

## Phase 6 — API Contract Planning

- [x] Plan `GET /api/v1/billing/plans`
- [x] Plan `GET /api/v1/billing/current-subscription`
- [x] Plan `POST /api/v1/billing/subscriptions`
- [x] Plan `POST /api/v1/billing/subscriptions/change-plan`
- [x] Plan `POST /api/v1/billing/subscriptions/cancel`
- [x] Plan `GET /api/v1/billing/usage`
- [x] Plan `GET /api/v1/billing/payments`
- [x] Plan `POST /api/v1/billing/payments`
- [x] Plan `GET /api/v1/billing/payments/{payment}`
- [x] Plan `GET /api/v1/billing/payments/{payment}/status`
- [x] Plan `GET /api/v1/billing/payments/{payment}/transactions`
- [x] Plan `POST /api/v1/billing/payments/{payment}/simulate/success`
- [x] Plan `POST /api/v1/billing/payments/{payment}/simulate/failure`
- [x] Plan `GET /api/v1/billing/payments/{payment}/webhooks`
- [x] Plan `POST /api/v1/billing/webhooks/{webhookDelivery}/retry`
- [x] Document API contract in `docs/billing/api.md`

---

## Phase 7 — Enums & Statuses Planning

- [x] Define `PlanType`
- [x] Define `SubscriptionStatus`
- [x] Define `PaymentStatus`
- [x] Define `PaymentTransactionType`
- [x] Define `WebhookDeliveryStatus`
- [x] Define `BillingFeature`
- [x] Define `UsagePeriod`
- [x] Define allowed payment status transitions
- [x] Define allowed subscription status transitions
- [x] Document statuses in `docs/billing/statuses.md`

---

## Phase 7.1 — Database Migrations & Seeders Implementation

- [x] Create `plans` migration
- [x] Create `plan_features` migration
- [x] Create `subscriptions` migration
- [x] Create `feature_usages` migration
- [x] Add indexes for billing tables
- [x] Add unique constraints for billing tables
- [x] Add foreign keys for billing tables
- [x] Add JSON columns for billing metadata
- [x] Add enum/status string columns for billing tables
- [x] Verify billing migration order
- [x] Run migrations in local Docker environment
- [x] Run migrations in testing database
- [x] Create `payments` migration
- [x] Create `payment_transactions` migration
- [x] Create `idempotency_keys` migration
- [x] Create `webhook_deliveries` migration
- [x] Add indexes for payment tables
- [x] Add unique constraints for payment tables
- [x] Add foreign keys for payment tables
- [x] Add JSON columns for payment metadata and payloads
- [x] Add enum/status string columns for payment tables
- [x] Verify payment migration order
- [x] Create billing plan seeder
- [x] Seed default Free plan
- [x] Seed Basic plan
- [x] Seed Pro plan
- [x] Seed Enterprise/Demo plan if needed
- [x] Seed default plan features
- [x] Ensure plan feature keys match `docs/billing/statuses.md`
- [x] Ensure seeded plan slugs match `docs/billing/plans.md`
- [x] Ensure seeders are safe to run repeatedly
- [x] Add seeder documentation if needed
- [x] Add migration tests for billing tables
- [x] Add migration tests for payment tables
- [x] Add seeder tests for default plans
- [x] Add seeder tests for default plan features
- [x] Verify `php artisan migrate:fresh --seed` works locally
- [x] Verify `php artisan migrate:fresh --env=testing --seed` works for tests
- [x] Verify existing test suite still passes or document known failures

---

Core Billing Models must not be implemented before Phase 7.1 creates and validates the billing/payment database schema and default plan seeders.

---

## Phase 8 — Core Billing Models

- [x] Create `Plan` model
- [x] Create `PlanFeature` model
- [x] Create `Subscription` model
- [x] Create `FeatureUsage` model
- [x] Add relations between Plan and PlanFeature
- [x] Add relations between User and Subscription
- [x] Add relations between Subscription and Plan
- [x] Add casts
- [x] Add fillable fields
- [x] Add factories
- [x] Add seeders for default plans
- [x] Add tests for billing models

---

## Phase 9 — Core Payment Models

- [x] Create `Payment` model
- [x] Create `PaymentTransaction` model
- [x] Create `IdempotencyKey` model
- [x] Create `WebhookDelivery` model
- [x] Add relations between Payment and Subscription
- [x] Add relations between Payment and PaymentTransaction
- [x] Add relations between Payment and WebhookDelivery
- [x] Add casts
- [x] Add fillable fields
- [x] Add factories
- [x] Add tests for payment models

---

## Phase 10 — Plan Access Service

- [x] Create `PlanService`
- [x] Create `SubscriptionService`
- [x] Create `FeatureAccessService`
- [x] Create `UsageLimitService`
- [x] Add method to check if user has active subscription
- [x] Add method to get current plan
- [x] Add method to check feature availability
- [x] Add method to check usage limit
- [x] Add method to increment feature usage
- [x] Add method to reset usage by period
- [x] Add tests for plan access rules
- [x] Add tests for usage limits

---

## Phase 10.1 — Billing Overrides & Restrictions

- [x] Design user billing blacklist
- [x] Design user payment blacklist
- [x] Design subscription-level feature overrides
- [x] Design user-level feature overrides if needed
- [x] Decide override priority over plan features
- [x] Add support for manual feature limit override
- [x] Add support for temporary feature override
- [x] Add support for override expiration
- [x] Add blocked billing access reason
- [x] Add blocked payment creation reason
- [x] Add admin/manual override notes
- [ ] Add activity logs for billing restrictions
- [ ] Add activity logs for manual overrides
- [x] Add tests for billing blacklist
- [x] Add tests for feature overrides
- [x] Document overrides in `docs/billing/overrides.md`

Billing overrides must remain module-agnostic. Chat and future dialer modules should use FeatureAccessService and must not implement their own billing override logic.

---

## Phase 10.2 — Billing RBAC Permissions

- [x] Define billing permission keys
- [x] Define payment permission keys
- [x] Define billing override/restriction permission keys
- [x] Define wallet/currency permission keys
- [x] Add billing permissions to permission seeder
- [x] Add payment permissions to permission seeder
- [x] Add override/restriction permissions to permission seeder
- [x] Add wallet/currency permissions to permission seeder
- [x] Assign billing/payment permissions to admin role
- [x] Assign override/restriction permissions to admin role
- [x] Assign wallet/currency permissions to admin role
- [x] Ensure normal users do not receive admin billing permissions by default
- [x] Ensure permissions are idempotent
- [x] Add tests that billing/payment permissions exist after seed
- [x] Add tests that admin role has billing/payment permissions
- [x] Add tests that normal users do not get admin billing permissions by default
- [x] Document billing RBAC in `docs/billing/rbac.md`

Billing RBAC permissions must be seeded before billing/payment API endpoints are exposed. Admin should receive billing management permissions, while normal users should access only their own billing data through ownership checks, not global admin permissions.

---

## Phase 11 — Paid Chat Features

- [x] Define free chat limits
- [x] Define paid chat limits
- [x] Define chat messages per day limit
- [x] Define chat webhooks limit
- [x] Define chat history retention limit
- [x] Define chat attachments limit if needed
- [x] Add feature checks before premium chat actions
- [x] Add usage increment after billable chat actions
- [x] Add API error for limit exceeded
- [x] Add activity log for limit exceeded
- [x] Add tests for free plan chat limits
- [x] Add tests for paid plan chat access
- [x] Add tests for usage tracking

Phase 11 implementation scope:
- Chat message creation is limited by `chat.messages.daily` and `chat.messages.monthly`.
- Chat attachment upload is limited by `chat.attachments.monthly`.
- Chat webhook endpoint creation is limited by `chat.webhook_endpoints.count`.
- Limit exceeded responses use stable code `feature_limit_exceeded`.
- Limit exceeded attempts are logged as `chat.feature_limit_exceeded`.
- Billing guards are inactive only when no billing plans exist yet; once a billing catalog exists, chat feature keys are enforced strictly.

---

## Phase 12 — Future Dialer Billing Foundation

- [x] Define reusable billing feature names for dialer
- [x] Define future `dialer.calls.monthly`
- [x] Define future `dialer.recordings.storage`
- [x] Define future `dialer.concurrent_calls`
- [x] Define future `dialer.webhooks`
- [x] Ensure billing system is not chat-only
- [x] Ensure feature access works for any module
- [x] Document future dialer billing extension in `docs/billing/future-dialer.md`

---

## Phase 12.1 — Currency & Exchange Rates Foundation

- [x] Design `currencies` table
- [x] Design `exchange_rates` table
- [x] Define base system currency
- [x] Define currency code format
- [x] Define currency display name
- [x] Define currency symbol
- [x] Define currency decimal precision
- [x] Define currency active/inactive state
- [x] Define currency comment/description field
- [x] Define exchange rate source
- [x] Define manual exchange rate mode
- [x] Define exchange rate validity period
- [x] Define currency conversion rules
- [x] Define rounding rules
- [x] Add currency seeders
- [x] Add tests for currencies
- [x] Add tests for exchange rates
- [x] Document currencies in `docs/billing/currencies.md`

Currency support is required before wallet balances and multi-currency payments. Exchange rates in this portfolio project can be manual/simulated and must not depend on real external providers.

---

## Phase 12.2 — User Wallet Balance

- [x] Design `wallets` table
- [x] Design `wallet_balances` table if needed
- [x] Design `wallet_transactions` table
- [x] Decide one wallet per user vs one wallet per currency
- [x] Add multi-currency balance support
- [x] Add available balance
- [x] Add held/reserved balance
- [x] Add wallet transaction types
- [x] Add wallet top-up transaction
- [x] Add wallet debit transaction
- [x] Add wallet refund transaction
- [x] Add wallet adjustment transaction
- [x] Add balance locking strategy
- [x] Add idempotency for wallet transactions
- [x] Add relation between wallet transactions and payments
- [x] Add relation between wallet transactions and subscriptions if needed
- [x] Add wallet activity logs
- [x] Add tests for wallet balance operations
- [x] Add tests for multi-currency wallet balances
- [x] Document wallet balance in `docs/billing/wallets.md`

Users may pay either from internal wallet balance or directly through a payment method. Wallet debits must be transactional and idempotent to avoid double-charging or granting access without a valid debit.

---

## Phase 12.3 — Payment Methods & User Payment Preferences

- [x] Design `payment_methods` table
- [x] Design `user_payment_preferences` table
- [x] Define fake card payment method
- [x] Define fake manual invoice payment method
- [x] Define fake wallet/internal balance payment method
- [x] Define payment method status
- [x] Define default payment method
- [x] Define payment strategy: wallet only
- [x] Define payment strategy: card/payment method only
- [x] Define payment strategy: wallet first with card fallback
- [x] Define payment strategy: manual approval only if needed
- [x] Store explicit user consent for saved payment method
- [x] Store explicit user consent for auto charge
- [x] Store explicit user consent for auto top-up
- [x] Add payment method metadata rules
- [x] Add payment method masking rules
- [x] Ensure no raw card data is stored
- [x] Add payment method seed/demo data if needed
- [x] Add tests for payment methods
- [x] Add tests for payment preferences
- [x] Document payment methods in `docs/billing/payment-methods.md`

Payment methods are simulated in this portfolio project. The system must model consent, masking, default method selection, and payment strategy, but must not store raw card data or real provider secrets.

---

## Phase 13 — Payment Creation Flow

- [x] Create `CreatePaymentRequest`
- [x] Create `CreatePaymentData` DTO
- [x] Create `PaymentService`
- [x] Add payment creation method
- [x] Validate subscription/payment context
- [x] Validate amount
- [x] Validate currency
- [x] Validate idempotency key
- [x] Accept payment source in `CreatePaymentRequest`
- [x] Accept payment strategy in `CreatePaymentData`
- [x] Resolve payment source from user preferences if not provided
- [x] Support payment source: direct payment method
- [x] Support payment source: internal wallet balance
- [x] Support payment source: wallet first with payment method fallback
- [x] Support direct payment method charge
- [x] Support internal wallet balance debit
- [x] Support wallet first with payment method fallback
- [x] Validate user payment preference
- [x] Validate saved payment method ownership
- [x] Validate saved payment method status
- [x] Resolve provider from payment method
- [x] Resolve provider account from user/customer settings if available
- [x] Fallback to platform provider config from `.env` if allowed
- [x] Use provider abstraction for payment method charges
- [x] Store provider key/source safely without storing secrets on payment
- [x] Store provider reference safely
- [x] Map provider response to internal payment status
- [x] Map provider errors to stable internal error codes
- [x] Ensure simulator provider is default in demo mode
- [x] Ensure external providers are disabled unless explicitly configured
- [x] Ensure customer-owned provider config cannot access another customer's credentials
- [x] Validate wallet balance before wallet debit
- [x] Create payment inside DB transaction
- [x] Create wallet debit transaction when paying from balance
- [x] Do not activate subscription if wallet debit fails
- [x] Link payment to wallet transaction if balance is used
- [x] Link payment to wallet transaction when wallet is used
- [x] Link payment to payment method when payment method is used
- [x] Create initial payment transaction record
- [x] Create activity log record
- [x] Return unified API response
- [x] Add feature tests for payment creation
- [x] Add validation tests

---

## Phase 13.1 — Payment Risk & Fraud Guard

- [x] Define payment risk rules
- [x] Add payment risk check before payment creation
- [x] Add user payment blacklist check
- [x] Add max failed payment attempts per period
- [x] Add max payment creation attempts per hour/day
- [x] Add suspicious payment activity flags
- [x] Add blocked payment reason
- [x] Add payment risk metadata
- [x] Add activity log for blocked payments
- [x] Add activity log for suspicious attempts
- [x] Ensure risk guard does not replace idempotency
- [x] Ensure idempotency still prevents duplicate payment creation
- [x] Add tests for payment blacklist
- [x] Add tests for failed-attempt limits
- [x] Add tests for suspicious activity blocking
- [x] Document payment risk guard in `docs/billing/payment-risk.md`

Payment Risk & Fraud Guard is a demo-safe risk layer for the simulator. It is not a real bank-grade antifraud system.

---

## Phase 13.2 — Auto Top-Up & Auto Charge

- [x] Design user payment method preference
- [x] Design auto top-up settings
- [x] Design auto charge settings
- [x] Allow user to choose wallet balance only
- [x] Allow user to choose card/payment method only
- [x] Allow user to choose wallet first, then card fallback
- [x] Allow user to enable/disable automatic charges
- [x] Add minimum wallet balance threshold
- [x] Add auto top-up amount
- [x] Add max auto top-up per day/month
- [x] Add failed auto top-up handling
- [x] Add auto charge consent tracking
- [x] Add activity log for auto charge consent changes
- [x] Add activity log for automatic balance top-up
- [ ] Add activity log for automatic subscription charge
- [x] Add tests for auto top-up disabled
- [x] Add tests for auto top-up enabled
- [x] Add tests for wallet-first payment strategy
- [x] Add tests for card-only payment strategy
- [x] Add tests for max auto top-up limits
- [x] Document auto top-up in `docs/billing/auto-top-up.md`

Auto top-up and auto charge require explicit user consent. In this simulator project, external payment provider behavior is fake, but consent, limits, idempotency, and audit logging must be modeled seriously.

---

## Phase 13.3 — Wallet/Card Payment API Interface

- [x] Plan `GET /api/v1/billing/wallet`
- [x] Plan `GET /api/v1/billing/wallet/balances`
- [x] Plan `GET /api/v1/billing/wallet/transactions`
- [x] Plan `POST /api/v1/billing/wallet/top-ups`
- [x] Plan `POST /api/v1/billing/wallet/debits` for internal/admin-safe use if needed
- [x] Plan `GET /api/v1/billing/payment-methods`
- [x] Plan `POST /api/v1/billing/payment-methods`
- [x] Plan `PATCH /api/v1/billing/payment-methods/{paymentMethod}`
- [x] Plan `DELETE /api/v1/billing/payment-methods/{paymentMethod}`
- [x] Plan `POST /api/v1/billing/payment-methods/{paymentMethod}/set-default`
- [x] Plan `GET /api/v1/billing/payment-preferences`
- [x] Plan `PATCH /api/v1/billing/payment-preferences`
- [x] Plan `POST /api/v1/billing/payments` with `payment_source`
- [x] Support `payment_source=wallet`
- [x] Support `payment_source=payment_method`
- [x] Support `payment_source=wallet_first`
- [x] Validate user payment strategy before payment creation
- [x] Validate wallet balance before wallet debit
- [x] Validate payment method availability before card/payment-method charge
- [x] Create wallet debit transaction when paying from balance
- [x] Create payment attempt when paying from card/payment method
- [x] Link payment to wallet transaction when wallet is used
- [x] Do not activate subscription if wallet debit fails
- [x] Do not activate subscription if payment method charge fails
- [x] Return stable API error for insufficient wallet balance
- [x] Return stable API error for missing payment method
- [x] Return stable API error for payment method not allowed
- [x] Require idempotency for wallet debit payment requests
- [x] Require idempotency for payment method charge requests
- [x] Add tests for wallet payment API
- [x] Add tests for card/payment-method payment API
- [x] Add tests for wallet-first fallback API
- [x] Add tests for payment preferences API
- [x] Document payment API interface in `docs/billing/payment-api.md`

This API layer must allow users to pay from internal wallet balance, from a saved/simulated payment method, or by wallet-first fallback depending on user preferences. All write operations that can create charges, wallet debits, or payment attempts must be idempotent.

---

## Phase 13.3.1 — Permission-Gated Wallet Adjustments API

- [x] Define manual wallet adjustment purpose
- [x] Add permission-gated `POST /api/v1/billing/wallet-adjustments`
- [x] Add granular wallet adjustment permissions
- [x] Ensure authenticated users without permission cannot adjust balances
- [x] Enforce direction-aware credit/debit authorization
- [x] Allow future operator/support roles through permissions instead of an admin namespace
- [x] Require reason and `Idempotency-Key`
- [x] Support optional safe description, reference, and metadata
- [x] Implement wallet adjustment credit through `WalletTransactionService`
- [x] Implement wallet adjustment debit through `WalletTransactionService`
- [x] Store actor ID and target user ID
- [x] Preserve append-only ledger entries and balance snapshots
- [x] Block insufficient wallet adjustment debit
- [x] Reject unsafe payment and secret metadata
- [x] Prevent duplicate balance mutation and detect idempotency conflicts
- [x] Add wallet adjustment credit/debit activity logs
- [x] Add targeted API, service, wallet regression, and RBAC tests
- [x] Document wallet adjustments in `docs/billing/wallet-adjustments.md`
- [ ] Define and enforce payment-source permissions during payment API hardening
- [ ] Define and enforce provider-specific use permissions when real provider adapters are implemented

Manual wallet adjustments are audited, permission-gated billing operations. They must never bypass the wallet ledger, create a payment implicitly, or expose a public user debit endpoint. Future payment-source and provider permissions remain planned hardening work.

---

## Phase 13.4 — External Payment Provider Integration Readiness

- [x] Design `PaymentProviderInterface`
- [x] Design `PaymentProviderFactory`
- [x] Design provider request DTOs
- [x] Design provider response DTOs
- [x] Design provider error DTOs
- [x] Design provider webhook DTOs
- [x] Design provider capability map
- [x] Design provider configuration contract
- [x] Design platform-level provider config from `.env`
- [x] Design customer-level provider config from database
- [x] Design `payment_provider_accounts` table
- [x] Design encrypted provider credentials storage
- [x] Design provider config source priority
- [x] Support config source: platform `.env`
- [x] Support config source: customer database settings
- [x] Support config source: disabled provider
- [x] Add provider account status
- [x] Add provider account test/live mode flag
- [x] Add provider account owner relation if needed
- [x] Add provider credentials validation rules
- [x] Add provider credentials masking rules
- [x] Add provider credentials metadata safety rules
- [x] Add admin form readiness notes for provider settings
- [x] Add simulator provider adapter
- [x] Add fake provider charge flow
- [x] Add fake provider refund flow if needed
- [x] Add fake provider payment status lookup
- [x] Add fake provider webhook verification
- [x] Add provider timeout/retry rules
- [x] Add provider error mapping
- [x] Add provider idempotency forwarding rules
- [x] Add provider metadata sanitization rules
- [x] Add provider webhook signature verification contract
- [ ] Add provider-specific documentation folder structure
- [ ] Add provider integration template README
- [x] Add planned Stripe adapter notes
- [x] Add planned PayPal adapter notes
- [x] Add planned LiqPay adapter notes
- [x] Add planned WayForPay adapter notes
- [x] Add planned Monobank/Fondy adapter notes if useful
- [x] Ensure no real provider secrets are committed
- [x] Ensure real external charges are disabled in portfolio/demo mode
- [x] Add tests for simulator provider adapter
- [x] Add tests for provider factory
- [x] Add tests for provider config resolver
- [x] Add tests for env-based provider config
- [x] Add tests for DB-based provider config
- [x] Add tests for encrypted credential masking
- [x] Add tests for provider error mapping
- [x] Add tests for fake webhook verification
- [x] Document provider integration readiness in `docs/billing/payment-providers.md`

This project uses a payment gateway simulator by default. Real payment providers are intentionally not connected in portfolio/demo mode. The architecture must support provider adapters, platform-level `.env` credentials, customer-level database credentials, encrypted credential storage, webhook verification, idempotency propagation, and provider-specific documentation/templates for Stripe/PayPal/LiqPay/WayForPay-style integrations.

---

## Phase 13.5 — Provider Adapter Template & Documentation

- [x] Create provider adapter folder convention
- [x] Create simulator provider folder
- [x] Create provider template folder
- [x] Create provider README template
- [x] Create provider capabilities template
- [x] Create provider config example template
- [x] Create provider webhook verification guide template
- [x] Create provider error mapping guide template
- [x] Create provider testing checklist template
- [x] Document how to add a new payment provider
- [x] Document required provider adapter methods
- [x] Document required provider DTOs
- [x] Document required provider tests
- [x] Document required provider environment variables
- [x] Document required DB configuration fields
- [x] Document safe credential storage rules
- [x] Document demo/sandbox/live mode difference
- [x] Add example provider skeleton for `Simulator`
- [x] Add placeholder docs for `Stripe`
- [x] Add placeholder docs for `PayPal`
- [x] Add placeholder docs for `LiqPay`
- [x] Add placeholder docs for `WayForPay`
- [x] Add placeholder docs for `PrivatBank / Privat24`
- [x] Add placeholder docs for `UKRSIBBANK`
- [x] Add placeholder docs for `Oschadbank`
- [x] Add tests or static checks for provider documentation if useful

Adding a new provider should be repeatable: create a provider folder, implement the provider interface, define capabilities, document configuration fields, map errors, implement webhook verification, and add provider-specific tests. This phase prepares the project so future providers can be added without changing core payment logic.

---

## Phase 14 — Idempotency Support

- [x] Require `Idempotency-Key` for payment creation
- [x] Create `IdempotencyService`
- [x] Generate deterministic safe request hash
- [x] Store hashed idempotency key by user and scope
- [x] Store safe response body
- [x] Store response status
- [x] Return previous response for same key and same payload
- [x] Reject same key with different payload
- [x] Block active processing requests
- [x] Add idempotency TTL and expired-record restart
- [x] Prevent duplicate payments
- [x] Prevent duplicate wallet debit
- [x] Prevent duplicate payment method charge
- [x] Prevent duplicate wallet-first fallback charge
- [x] Prevent duplicate wallet top-up
- [x] Prevent duplicate wallet adjustment
- [x] Prevent duplicate auto top-up
- [x] Prevent duplicate auto charge
- [x] Store idempotency relation to payment/payment-method charge
- [x] Store idempotency relation to wallet transaction
- [x] Add payment source permissions to seeder
- [x] Add provider usage permissions to seeder
- [x] Add idempotency permissions to seeder
- [x] Add tests for payment source/provider/idempotency permissions
- [x] Add tests for idempotency replay
- [x] Add tests for idempotency conflict
- [x] Add tests for duplicate prevention
- [x] Document idempotency in `docs/billing/idempotency.md`

Payment source/provider permissions are seeded as RBAC readiness and are not enforced on normal user payment flows yet. Future real provider adapters must add `provider.charge` idempotency forwarding without storing or forwarding unsafe metadata.

---

## Phase 14.1 — Company / Seller Ownership Scope

- [x] Define company ownership model
- [x] Define seller/merchant ownership model
- [x] Define customer/end-user relationship to seller
- [x] Design `companies` table
- [x] Design `sellers` table
- [x] Design `company_users` table
- [x] Design `seller_customers` table
- [x] Add company model
- [x] Add seller model
- [x] Add company user membership model if needed
- [x] Add seller customer relation model if needed
- [x] Add owner/scope fields to payments
- [x] Add payer user relation to payments
- [x] Add seller relation to payments
- [x] Add company relation to payments
- [x] Add provider account ownership relation to seller/company if needed
- [x] Define payment ownership resolving rules
- [x] Define provider account resolving rules by seller/company/platform
- [x] Define reporting scope rules for company
- [x] Define reporting scope rules for seller
- [x] Define reporting scope rules for customer
- [x] Add company/seller RBAC permissions
- [x] Add company/seller permissions to seeder
- [x] Ensure admin receives company/seller management permissions
- [x] Ensure normal users do not receive company/seller management permissions by default
- [x] Add ownership scope service
- [x] Add tests for company/seller models
- [x] Add tests for payment ownership assignment
- [x] Add tests for provider account ownership isolation if implemented
- [x] Add tests for company/seller permission seeding
- [x] Document ownership scope in `docs/billing/ownership-scope.md`

Company / Seller ownership scope prepares the billing platform for reports, provider account isolation, webhooks, and future multi-merchant flows. It must be additive and must not break existing user-scoped payments.

---

## Phase 14.1.1 — Company / Seller Demo Seeders

- [x] Add company/seller demo seeder
- [x] Seed demo company
- [x] Seed demo seller
- [x] Seed demo company membership
- [x] Seed demo seller customer relation
- [x] Ensure ownership seeders are idempotent
- [x] Add tests for company/seller seeders
- [x] Document ownership demo seed data

The demo ownership graph is deterministic and local/demo-safe. It must not convert existing users, modify payment data, or store real provider credentials.

---

## Phase 15 — Payment Simulation Flow

- [x] Create `PaymentSimulationService`
- [x] Add success simulation
- [x] Add failure simulation
- [x] Add invalid state protection
- [x] Add payment row locking if needed
- [x] Mark payment as succeeded
- [x] Mark payment as failed
- [x] Store failure reason
- [x] Create payment transaction for success
- [x] Create payment transaction for failure
- [ ] Activate subscription after successful payment
- [x] Do not activate subscription after failed payment
- [ ] Dispatch webhook job after payment status change
- [x] Add tests for successful payment
- [x] Add tests for failed payment
- [x] Add tests for invalid state transitions
- [x] Add simulation endpoints
- [x] Add permission checks for simulation
- [x] Add activity log for simulated success
- [x] Add activity log for simulated failure
- [x] Document simulation flow in `docs/billing/payment-simulation.md`

Subscription activation is implemented in Phase 19 through payment events. Webhook dispatch remains Phase 16. Phase 15 owns simulator-safe payment state transitions, transaction history, and activity logs.

---

## Phase 16 — Webhook Delivery

- [ ] Create `WebhookPayloadBuilder`
- [x] Create `WebhookDeliveryService`
- [x] Create `SendPaymentWebhookJob`
- [x] Create webhook delivery record
- [x] Send payment success webhook
- [x] Send payment failure webhook
- [x] Store webhook payload
- [x] Store response status
- [x] Store response body
- [x] Store attempts count
- [x] Mark webhook as delivered
- [x] Mark webhook as failed
- [x] Configure retry attempts
- [x] Configure backoff
- [ ] Design inbound provider webhook endpoint
- [ ] Design inbound provider webhook verification
- [ ] Resolve provider account for inbound webhook
- [ ] Verify provider webhook signature if provider supports it
- [ ] Map provider webhook event to internal billing event
- [ ] Ignore duplicate provider webhook events safely
- [ ] Store provider webhook reference
- [ ] Store provider account reference for webhook event
- [x] Add manual retry endpoint
- [x] Add tests for webhook job dispatch
- [x] Add tests for successful webhook delivery
- [x] Add tests for failed webhook delivery
- [ ] Add tests for fake provider webhook verification
- [x] Add webhook listing endpoint
- [x] Add webhook signature
- [x] Add permanently failed status
- [x] Add webhook delivery activity logs
- [x] Add tests for webhook retry
- [x] Document webhooks in `docs/billing/webhooks.md`

Phase 16 implements outbound billing webhooks only. Inbound provider webhook endpoint, provider signature verification, provider event mapping, and fake provider webhook verification remain future provider-specific work because real providers are intentionally disabled in demo mode. `WebhookPayloadBuilder` remains unchecked because the current payload builder is private inside `WebhookDeliveryService`; extract it only if webhook event families expand beyond payments.

---

## Phase 17 — Invoice Flow

- [x] Design invoices table
- [x] Design invoice_items table
- [x] Create Invoice model
- [x] Create InvoiceItem model
- [x] Add invoice relations
- [x] Add invoice item relations
- [x] Add invoice lifecycle statuses
- [x] Add invoice total calculation
- [x] Add invoice issuing
- [x] Add invoice voiding
- [x] Add payment pending state
- [x] Link invoice to payment
- [x] Link invoice to subscription if needed
- [x] Add ownership scope to invoices
- [x] Add invoice permissions
- [x] Add invoice activity logs
- [x] Add tests for invoices
- [x] Add tests for invoice payment flow
- [x] Document invoice flow in `docs/billing/invoices.md`
- [x] Add invoice API endpoints
- [x] Add invoice resources/requests
- [x] Add invoice API tests

---

## Phase 17.1 - Billing Domain Events & Post-Event Actions

- [x] Define billing domain event purpose
- [x] Define post-event action boundaries
- [x] Define payment lifecycle events
- [x] Define invoice lifecycle events
- [x] Define wallet lifecycle events
- [x] Define future subscription lifecycle events
- [x] Create `PaymentCreated` event
- [x] Create `PaymentSucceeded` event
- [x] Create `PaymentFailed` event
- [x] Create `PaymentExpired` event
- [x] Create `PaymentCancelled` event
- [x] Create `InvoiceIssued` event
- [x] Create `InvoicePaymentPending` event
- [x] Create `InvoicePaid` event
- [x] Create `InvoiceFailed` event
- [x] Create `WalletCredited` event
- [x] Create `WalletDebited` event
- [x] Add listener structure for payment notifications
- [x] Add listener structure for invoice notifications
- [x] Add listener structure for receipt/document generation
- [x] Add listener structure for webhook dispatch
- [x] Add listener structure for seller/company notifications
- [x] Add future hook for subscription activation
- [x] Add placeholder job for receipt/document generation
- [x] Add placeholder job for SMS notification
- [x] Add placeholder job for email notification
- [x] Ensure post-event actions are queued where appropriate
- [x] Ensure post-event actions do not break payment transactions
- [x] Ensure event payloads are safe and do not expose secrets
- [x] Ensure idempotency/replay does not duplicate post-event actions
- [x] Add tests for payment events
- [x] Add tests for invoice events
- [x] Add tests for wallet events
- [x] Add tests that failed payment can trigger failure actions
- [x] Add tests that repeated idempotent replay does not duplicate actions
- [x] Document billing events in `docs/billing/payment-events.md`

Billing events must support more than successful payments. The event layer prepares the project for receipts, SMS/email notifications, webhooks, seller/company notifications, documents/checks, and future subscription lifecycle actions without coupling those side effects directly to PaymentService or InvoiceService.

---

## Phase 18 - Cron / Scheduler

- [x] Configure Laravel scheduler in Docker if not configured
- [x] Add scheduled command for expired pending payments
- [x] Add scheduled command for usage reset
- [x] Add scheduled command for subscription expiration check
- [x] Add scheduled command for failed webhook retry if needed
- [x] Add scheduled command for billing cleanup if needed
- [x] Add command tests
- [x] Add scheduler docs
- [x] Document cron architecture in `docs/billing/scheduler.md`

---

## Phase 19 — Subscription Lifecycle

- [x] Create subscription in pending state before payment if needed
- [x] Activate subscription after successful payment
- [x] Keep subscription inactive after failed payment
- [x] Handle plan upgrade
- [x] Handle plan downgrade
- [x] Handle subscription cancellation
- [x] Handle subscription expiration
- [x] Handle subscription renewal simulation
- [x] Renew subscription from wallet balance if user preference allows
- [x] Renew subscription by automatic payment method charge if user consent exists
- [x] Keep subscription past_due if wallet/card renewal fails
- [x] Add activity log for automatic renewal attempt
- [x] Create activity logs for subscription changes
- [x] Add tests for subscription activation
- [x] Add tests for subscription cancellation
- [x] Add tests for expired subscription

---

## Phase 20 — Activity Logging

- [ ] Log plan viewed if needed
- [x] Log subscription created
- [x] Log subscription activated
- [x] Log subscription cancelled
- [x] Log payment created
- [x] Log payment succeeded
- [x] Log payment failed
- [x] Log idempotency replay
- [x] Log idempotency conflict
- [x] Log webhook dispatched
- [x] Log webhook delivered
- [x] Log webhook failed
- [x] Log usage limit exceeded
- [x] Add tests for critical activity logs

Plan viewed is intentionally not logged because catalog browsing can create high-volume noise; plan selection, payment creation, and subscription lifecycle actions are logged instead.

---

## Phase 21 — Unified API Response & Errors

- [x] Review current BaseController/API response format
- [x] Decide final response contract
- [x] Ensure billing endpoints use unified success response
- [x] Ensure billing endpoints use unified error response
- [x] Add domain exceptions
- [x] Add payment already processed exception
- [x] Add invalid payment state exception
- [x] Add idempotency conflict exception
- [x] Add subscription inactive exception
- [x] Add feature limit exceeded exception
- [x] Add insufficient wallet balance exception
- [x] Add payment method not found exception
- [x] Add payment method not allowed exception
- [x] Add payment preference invalid exception
- [x] Add duplicate wallet debit exception
- [x] Add auto charge consent required exception
- [x] Add provider not configured exception
- [x] Add provider disabled exception
- [x] Add provider credentials invalid exception
- [x] Add provider account not found exception
- [x] Add provider account forbidden exception
- [x] Add provider charge failed exception
- [x] Add provider timeout exception
- [x] Add provider webhook signature invalid exception
- [x] Add provider unsupported operation exception
- [x] Add tests for API errors
- [x] Document error responses

---

## Phase 22 — API Documentation

- [x] Update main README with billing module description
- [x] Add `docs/billing/overview.md`
- [x] Add `docs/billing/api.md`
- [x] Add `docs/billing/plans.md`
- [x] Add `docs/billing/idempotency.md`
- [x] Add `docs/billing/webhooks.md`
- [x] Add `docs/billing/scheduler.md`
- [x] Add `docs/billing/testing.md`
- [x] Add curl examples
- [x] Add example payment flow
- [x] Add example subscription flow
- [x] Add example chat limit flow
- [x] Add future dialer billing notes
- [x] Add wallet balance API examples
- [x] Add wallet top-up API examples
- [x] Add wallet payment API examples
- [x] Add card/payment method API examples
- [x] Add wallet-first fallback API examples
- [x] Add payment preferences API examples
- [x] Add auto-charge consent API examples
- [x] Add provider abstraction documentation
- [x] Add simulator provider examples
- [x] Add platform `.env` provider config examples
- [x] Add customer DB provider config examples
- [x] Add provider account admin form examples
- [x] Add encrypted credentials documentation
- [x] Add planned Stripe integration notes
- [x] Add planned PayPal integration notes
- [x] Add planned LiqPay integration notes
- [x] Add planned WayForPay integration notes
- [x] Add provider webhook verification examples
- [x] Add provider error mapping examples
- [x] Add provider adapter template documentation

---

## Phase 22.1 - Billing User Portal UI

- [x] Design user billing dashboard page
- [x] Show current subscription
- [x] Show current plan and limits
- [x] Show available plans
- [x] Show usage limits and remaining usage
- [x] Show payment history
- [x] Show invoice history
- [x] Show wallet balance
- [x] Show wallet transaction history
- [x] Show payment methods
- [x] Allow user to add simulator payment method
- [x] Allow user to set default payment method
- [x] Allow user to remove/deactivate payment method
- [x] Show payment preferences
- [x] Allow user to choose wallet-only strategy
- [x] Allow user to choose card/payment-method-only strategy
- [x] Allow user to choose wallet-first strategy
- [x] Allow user to enable/disable auto-charge
- [x] Allow user to enable/disable auto top-up
- [x] Show auto-charge consent state
- [x] Show auto top-up settings
- [x] Add loading/empty/error states
- [x] Add frontend tests if project structure supports it
- [x] Document user billing portal UI

Note:
This phase makes billing visible to end users. It should consume existing billing APIs and must not duplicate billing business logic on the frontend.

---

## Phase 22.2 - Billing Checkout / Payment UI

- [x] Design checkout page for plan purchase
- [x] Design invoice payment page
- [x] Design wallet top-up page
- [x] Support payment source: wallet
- [x] Support payment source: payment method
- [x] Support payment source: wallet-first
- [x] Show invoice summary before payment
- [x] Show payment amount and currency
- [x] Show selected seller/company context if present
- [x] Show payment status after creation
- [x] Show pending payment state
- [x] Show succeeded payment state
- [x] Show failed payment state
- [x] Show expired/cancelled payment state
- [x] Add button to simulate payment success in demo/admin mode if allowed
- [x] Add button to simulate payment failure in demo/admin mode if allowed
- [x] Prevent duplicate submit with UI lock/idempotency key
- [x] Display stable API error messages
- [x] Add frontend tests if project structure supports it
- [x] Document checkout/payment UI flow

Note:
Checkout UI must use idempotency keys and existing payment APIs. It must not call provider logic directly.
Simulator actions use payment UUID and remain permission/demo-gated. Normal users must not bypass backend permissions.

---

## Phase 22.3 - Admin / Operator Billing Management UI

- [x] Design admin billing dashboard
- [x] Show payments list
- [x] Show payment details
- [x] Show payment transaction history
- [x] Show invoices list
- [x] Show invoice details
- [ ] Show subscriptions list
- [x] Show subscription details
- [x] Show wallet balances by user
- [x] Show wallet transaction history by user
- [x] Add permission-gated wallet adjustment UI
- [x] Allow manual wallet credit with required reason
- [x] Allow manual wallet debit with required reason
- [x] Show activity logs for billing entities
- [x] Show webhook deliveries
- [x] Allow manual webhook retry if permission exists
- [x] Show idempotency records if permission exists
- [x] Show provider accounts
- [ ] Add provider account form readiness UI
- [x] Show billing restrictions / blacklist
- [ ] Add billing restriction creation UI
- [ ] Add billing restriction disable/expire UI
- [x] Show feature overrides
- [ ] Add feature override creation UI
- [ ] Add feature override disable/expire UI
- [x] Enforce frontend permission checks
- [x] Enforce backend permission checks through API
- [x] Add loading/empty/error states
- [x] Add frontend tests if project structure supports it
- [x] Document admin/operator billing UI

Note:
Admin / Operator Billing Management UI is focused on operational history and safe management actions: payments, transactions, invoices, subscriptions, wallets, webhooks, activity logs, idempotency, provider account readiness, restrictions, and feature overrides. Financial analytics and revenue reports are intentionally separated into a dedicated reporting phase.

Read-only admin surfaces are implemented for payments list/detail/transactions, subscription detail lookup, wallet-by-user screens, idempotency records, provider accounts, restrictions, feature overrides, and webhook delivery review. Any deferred safe-management CRUD items are tracked in the Future Billing Roadmap section below.

Frontend permission checks are only UX helpers. Backend permissions remain the source of truth.

---

## Phase 22.3.1 - Admin Billing Backend API & Demo Data

Checklist:

- [x] Design admin billing backend API scope
- [x] Add admin payments list endpoint
- [x] Add admin payment detail endpoint
- [x] Add admin payment transaction history endpoint
- [x] Add admin subscriptions list endpoint
- [x] Add admin subscription detail endpoint if needed
- [x] Add admin wallet balance by user endpoint
- [x] Add admin wallet transaction history by user endpoint
- [x] Add admin idempotency records list endpoint
- [x] Add admin idempotency record detail endpoint
- [x] Ensure raw idempotency keys are never exposed
- [x] Add admin provider accounts list endpoint
- [x] Add admin provider account detail endpoint
- [ ] Add provider account form readiness API if safe
- [x] Ensure provider credentials are masked and never exposed raw
- [x] Add billing restrictions / blacklist list endpoint
- [ ] Add billing restriction create endpoint
- [ ] Add billing restriction disable/expire endpoint
- [x] Add feature overrides list endpoint
- [ ] Add feature override create endpoint
- [ ] Add feature override disable/expire endpoint
- [x] Add backend permission checks for all admin billing endpoints
- [x] Add permissions for admin billing read/manage actions if missing
- [x] Ensure normal users cannot access admin billing endpoints
- [x] Add public UUID payment simulation endpoint if still needed
- [x] Keep simulation permission/demo gated
- [x] Add demo admin/operator user seed data
- [x] Add demo normal user seed data
- [x] Add demo company/seller/customer seed data if useful
- [x] Add demo payments in multiple statuses
- [x] Add demo payment transactions
- [x] Add demo subscriptions in multiple statuses
- [x] Add demo wallets and wallet transactions
- [x] Add demo invoices in multiple statuses
- [x] Add demo webhook deliveries
- [x] Add demo simulator provider accounts
- [x] Add demo billing restrictions / blacklist entries
- [x] Add demo feature overrides
- [x] Split demo billing seed data into modular local-only seeders
- [x] Make demo seeders idempotent and safe to rerun
- [x] Update Angular admin billing UI to consume new endpoints
- [x] Update Angular checkout UI simulation buttons if endpoint is added
- [x] Add backend tests for admin billing APIs
- [x] Add backend tests for permissions
- [x] Add backend tests for demo seeders
- [x] Add frontend tests for updated admin billing UI
- [x] Add frontend tests for checkout simulation buttons if implemented
- [x] Document admin billing backend APIs
- [x] Document demo seed data
- [x] Document admin permissions
- [x] Update Phase 22.3 checklist after real endpoints are implemented

Note:
This phase closes backend/API gaps for the Admin / Operator Billing Management UI. It is focused on operational history and safe admin actions, not financial analytics. Reports and revenue aggregates belong to Phase 22.6 and Phase 22.6.1.

---

## Phase 22.4 - Seller / Company Billing Views

- [x] Design company billing overview page
- [x] Design seller billing overview page
- [x] Show company-scoped payment history
- [x] Show seller-scoped payment history
- [x] Show company-scoped invoices
- [x] Show seller-scoped invoices
- [x] Show company-scoped customers if available
- [x] Show seller customers
- [x] Show seller revenue/payment summary
- [x] Show company revenue/payment summary
- [x] Show provider account status for seller/company
- [x] Show webhook delivery status for seller/company payments
- [x] Add filters by date/status/currency/seller/customer
- [x] Add export-ready table structure if useful
- [x] Enforce ownership scope in UI/API calls
- [x] Add tests if project structure supports it
- [x] Document seller/company billing views

Note:
These views prepare the project for reporting, but full reports/analytics can remain a later phase.
They are intentionally implemented as frontend ownership shells with explicit gap notes until scoped backend list endpoints exist.

---

## Phase 22.5 - Billing Demo Flows

- [x] Add demo flow for free plan limits
- [x] Add demo flow for paid plan purchase
- [x] Add demo flow for wallet top-up
- [x] Add demo flow for wallet payment
- [x] Add demo flow for payment method payment
- [x] Add demo flow for wallet-first fallback
- [x] Add demo flow for invoice payment
- [x] Add demo flow for subscription activation
- [x] Add demo flow for failed payment
- [x] Add demo flow for webhook delivery history
- [x] Add demo flow for billing restriction / blacklist
- [x] Add demo flow for feature override
- [x] Add demo flow for seller/company scoped payment
- [x] Add screenshots or notes for portfolio README if useful
- [x] Document demo billing flows

Note:
Demo flows should make the portfolio easy to review without requiring Postman.

---

## Phase 22.6 - Billing Reports & Analytics UI

- [x] Design billing reports dashboard
- [x] Show revenue summary by period
- [x] Show successful payments summary
- [x] Show failed payments summary
- [x] Show pending payments summary
- [x] Show revenue by plan
- [x] Show revenue by currency
- [x] Show revenue by seller/company
- [ ] Show subscription MRR/ARR if supported
- [x] Show active subscriptions count
- [x] Show past_due subscriptions count
- [x] Show cancelled/expired subscriptions count
- [x] Show wallet top-up totals
- [x] Show wallet debit totals
- [x] Show invoice paid/unpaid totals
- [x] Add date range filters
- [x] Add status filters
- [x] Add currency filters
- [x] Add seller/company filters
- [x] Add export-ready tables
- [ ] Add CSV export if backend supports it
- [x] Add backend reporting API gap notes where endpoints are missing
- [x] Ensure reports do not calculate authoritative revenue from partial frontend pages
- [x] Add frontend tests if project structure supports it
- [x] Document billing reports and analytics UI

Note:
Reports are separate from operational admin history. The frontend must not calculate authoritative financial totals from partial paginated lists. Real report totals require dedicated backend reporting endpoints.
Deferred report analytics and export extensions are tracked in the Future Billing Roadmap section below. When export is implemented, it should use an extensible export layer rather than a CSV-only implementation, so additional formats such as CSV, XLSX, PDF, JSON, or other project-specific formats can be added later without rewriting report queries or the reports dashboard.

---

## Phase 22.6.1 - Billing Reports Backend API

- [x] Design billing reports API contract
- [x] Add revenue summary endpoint
- [x] Add payment status summary endpoint
- [x] Add revenue by plan endpoint
- [x] Add revenue by currency endpoint
- [x] Add revenue by seller/company endpoint
- [x] Add subscription metrics endpoint
- [x] Add invoice metrics endpoint
- [x] Add wallet metrics endpoint
- [x] Add date/status/currency/seller/company filters
- [x] Add permission checks for report access
- [x] Add tests for report totals
- [x] Add tests for report permissions
- [x] Ensure financial reports use database aggregates, not frontend calculations
- [x] Document reports API

Note:
This backend phase is needed before reports UI can show authoritative financial totals.

---

## Phase 22 Final Status

- [x] Billing documentation is portfolio-ready
- [x] User billing portal is portfolio-ready
- [x] Checkout/payment UI is portfolio-ready
- [x] Admin read-only operational billing surfaces are portfolio-ready
- [x] Demo billing flows are portfolio-ready
- [x] Reports backend API is portfolio-ready
- [x] Reports analytics UI is portfolio-ready

Note:
Phase 22 is portfolio-ready. Advanced safe-management CRUD, extensible exports, MRR/ARR, and full seller/company backend reporting are intentionally tracked in the Future Billing Roadmap instead of blocking the portfolio-ready billing module.

---

## Future Billing Roadmap

These items are intentionally deferred production extensions. They should not block Phase 22 portfolio readiness.

### Safe Management CRUD

- [ ] Add provider account readiness API/UI
- [ ] Add billing restriction create endpoint
- [ ] Add billing restriction disable/expire endpoint
- [ ] Add billing restriction creation UI
- [ ] Add billing restriction disable/expire UI
- [ ] Add feature override create endpoint
- [ ] Add feature override disable/expire endpoint
- [ ] Add feature override creation UI
- [ ] Add feature override disable/expire UI
- [ ] Add required reason fields for all mutating safe-management actions
- [ ] Add activity logs for all mutating safe-management actions
- [ ] Add permission tests for all mutating safe-management actions
- [ ] Document safe-management CRUD flows

### Admin Subscription Management

- [ ] Add subscriptions list UI in admin billing dashboard
- [ ] Add filters for admin subscriptions list
- [ ] Add pagination for admin subscriptions list
- [ ] Add frontend tests for admin subscriptions list

### Extensible Reports Export Layer

- [ ] Design reusable reports export contract
- [ ] Add backend export endpoint with validated format parameter
- [ ] Add CSV export format
- [ ] Add XLSX export format
- [ ] Add PDF export format if needed
- [ ] Add JSON export format
- [ ] Add support for project-specific export formats
- [ ] Reuse backend report filters and aggregate queries for exports
- [ ] Ensure frontend does not calculate exported financial totals locally
- [ ] Add queueable or streamed export strategy for large exports if needed
- [ ] Add backend permission checks for exports
- [ ] Add tests for export permissions
- [ ] Add tests for export format selection
- [ ] Document extensible export architecture

### Subscription Analytics

- [ ] Add authoritative subscription interval pricing model
- [ ] Add MRR calculation
- [ ] Add ARR calculation
- [ ] Add churn metrics if needed
- [ ] Add cancelled/expired subscription analytics
- [ ] Add tests for subscription analytics
- [ ] Document MRR/ARR calculation rules and limitations

### Seller / Company Backend Reporting

- [ ] Add full company-scoped backend payment list endpoint
- [ ] Add full seller-scoped backend payment list endpoint
- [ ] Add full company-scoped invoice list endpoint
- [ ] Add full seller-scoped invoice list endpoint
- [ ] Add company/seller scoped reporting permissions
- [ ] Add company/seller scoped report tests
- [ ] Document seller/company backend reporting

---

## Phase 23 — Testing Strategy

- [ ] Add feature tests for plans API
- [ ] Add feature tests for current subscription API
- [x] Add feature tests for subscription creation
- [x] Add feature tests for payment creation
- [x] Add feature tests for payment success
- [x] Add feature tests for payment failure
- [x] Add feature tests for transaction history
- [x] Add feature tests for webhooks
- [x] Add feature tests for idempotency
- [x] Add feature tests for paid chat limits
- [x] Add feature tests for wallet balance API
- [x] Add feature tests for wallet top-up API
- [x] Add feature tests for wallet debit API
- [x] Add feature tests for payment methods API
- [x] Add feature tests for payment preferences API
- [x] Add feature tests for wallet-only payment strategy
- [x] Add feature tests for card-only payment strategy
- [x] Add feature tests for wallet-first fallback strategy
- [x] Add idempotency tests for wallet debit
- [x] Add idempotency tests for payment method charge
- [x] Add unit tests for services
- [ ] Add unit tests for DTOs
- [ ] Add unit tests for webhook payload builder
- [x] Add unit tests for `PaymentProviderInterface` implementations
- [x] Add unit tests for provider factory
- [x] Add unit tests for provider config resolver
- [x] Add unit tests for env-based provider configuration
- [x] Add unit tests for DB-based provider configuration
- [x] Add unit tests for encrypted credential masking
- [x] Add unit tests for provider response mapping
- [x] Add unit tests for provider error mapping
- [x] Add feature tests for simulator provider charge flow
- [x] Add feature tests for simulator provider webhook verification
- [x] Add tests that real providers are disabled in demo mode
- [x] Add tests that no real provider secrets are required for local setup
- [x] Add tests that customer provider credentials are isolated
- [x] Add tests that one customer cannot use another customer's provider account
- [x] Add command tests for scheduler tasks
- [x] Add queue fake tests
- [x] Add HTTP fake tests
- [x] Add test docs

Note:
Phase 23 tracks test coverage, not feature implementation. Items remain unchecked when the feature is future work, when coverage is only partial, or when a dedicated unit boundary has not been extracted yet.
Plans API coverage is intentionally future because the endpoint is not implemented yet.
Current subscription API and DTO-only coverage remain partial because they are exercised through broader billing flows rather than isolated dedicated boundaries.
Webhook payload builder coverage stays future until that class is extracted.

---

## Phase 24 — Docker & DevOps Polish

- [x] Verify backend container
- [x] Verify nginx container
- [x] Verify mysql container
- [x] Verify redis container
- [x] Verify queue-worker container
- [x] Add scheduler container or cron strategy if needed
- [x] Review old `saas_*` names
- [x] Decide whether to rename containers
- [x] Keep frontend containers untouched unless needed
- [x] Document Docker commands
- [x] Document queue worker commands
- [x] Document scheduler commands
- [x] Add troubleshooting docs

Note:
The stack is already renamed to `payment_gateway_*` in active compose services. `horizon` remains optional, while `queue-worker` and `scheduler` cover the default development runtime. No destructive volume commands are required for routine operations.

---

## Phase 25.0 - Runtime Smoke Check & Screenshot Plan

- [x] Verify app boots from Docker
- [x] Verify backend billing routes are registered
- [x] Verify frontend build passes
- [x] Verify demo seed command and demo users
- [ ] Verify Vue admin billing launchpad route
- [ ] Verify user billing portal route
- [ ] Verify checkout/payment route
- [ ] Verify wallet top-up route
- [ ] Verify invoice payment route
- [ ] Verify admin billing route
- [ ] Verify admin reports route
- [ ] Verify demo flows route
- [ ] Verify company billing route
- [ ] Verify seller billing route
- [x] Create screenshot folder structure
- [x] Add Vue admin billing launchpad screenshot note
- [x] Add screenshot naming plan
- [x] Add diagram naming plan
- [x] Document manual smoke checklist

Note:
Phase 25.0 is a docs-first smoke plan. Browser verification and screenshot capture remain manual follow-up work for the README/portfolio polish phase.

---

## Phase 25 — Portfolio Polish

- [ ] Add README section: SaaS Billing Module
- [ ] Add README section: Payment Gateway Simulator
- [ ] Add README section: Idempotency
- [ ] Add README section: Webhooks
- [ ] Add README section: Queue & Scheduler
- [ ] Add README section: Paid Chat Features
- [ ] Add README section: Future Dialer Billing
- [ ] Add README section: Billing Admin Management UI
- [ ] Add README section: Billing Demo Flows
- [ ] Add README section: Billing Reports / Analytics Roadmap
- [ ] Explain admin billing backend API and demo seed data
- [ ] Explain operational admin history vs financial reports
- [ ] Add architecture diagram text
- [ ] Add interview talking points
- [ ] Add examples of senior-level decisions
- [ ] Explain why repository layer is not used initially
- [ ] Explain transaction boundaries
- [ ] Explain webhook retry strategy
- [ ] Explain idempotency strategy
- [ ] Add screenshots or GIF notes for billing user portal
- [ ] Add screenshots or GIF notes for checkout/payment flow
- [ ] Add screenshots or GIF notes for admin billing management
- [ ] Add screenshots or GIF notes for seller/company billing views
- [ ] Add screenshots or GIF notes for billing reports dashboard
- [ ] Add explanation of permission-gated wallet adjustments
- [ ] Add explanation of blacklist/restrictions and feature overrides
- [ ] Add explanation of demo payment simulator UI
- [ ] Explain difference between operational history and financial reports
