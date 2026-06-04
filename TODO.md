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
- [ ] Add wallet activity logs
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

- [ ] Require `Idempotency-Key` for payment creation
- [ ] Create `IdempotencyService`
- [ ] Generate request hash
- [ ] Store idempotency key
- [ ] Store response body
- [ ] Store response status
- [ ] Return previous response for same key and same payload
- [ ] Reject same key with different payload
- [ ] Prevent duplicate payments
- [ ] Prevent duplicate wallet debit
- [ ] Prevent duplicate payment method charge
- [ ] Prevent duplicate wallet-first fallback charge
- [ ] Prevent duplicate auto top-up
- [ ] Store idempotency relation to payment method charge if applicable
- [ ] Store idempotency relation to wallet transaction if applicable
- [ ] Add tests for idempotency replay
- [ ] Add tests for idempotency conflict
- [ ] Add tests for duplicate prevention
- [ ] Document idempotency in `docs/billing/idempotency.md`

---

## Phase 15 — Payment Simulation Flow

- [ ] Create `PaymentSimulationService`
- [ ] Add success simulation
- [ ] Add failure simulation
- [ ] Add invalid state protection
- [ ] Add payment row locking if needed
- [ ] Mark payment as succeeded
- [ ] Mark payment as failed
- [ ] Store failure reason
- [ ] Create payment transaction for success
- [ ] Create payment transaction for failure
- [ ] Activate subscription after successful payment
- [ ] Do not activate subscription after failed payment
- [ ] Dispatch webhook job after payment status change
- [ ] Add tests for successful payment
- [ ] Add tests for failed payment
- [ ] Add tests for invalid state transitions

---

## Phase 16 — Webhook Delivery

- [ ] Create `WebhookPayloadBuilder`
- [ ] Create `WebhookDeliveryService`
- [ ] Create `SendPaymentWebhookJob`
- [ ] Create webhook delivery record
- [ ] Send payment success webhook
- [ ] Send payment failure webhook
- [ ] Store webhook payload
- [ ] Store response status
- [ ] Store response body
- [ ] Store attempts count
- [ ] Mark webhook as delivered
- [ ] Mark webhook as failed
- [ ] Configure retry attempts
- [ ] Configure backoff
- [ ] Design inbound provider webhook endpoint
- [ ] Design inbound provider webhook verification
- [ ] Resolve provider account for inbound webhook
- [ ] Verify provider webhook signature if provider supports it
- [ ] Map provider webhook event to internal billing event
- [ ] Ignore duplicate provider webhook events safely
- [ ] Store provider webhook reference
- [ ] Store provider account reference for webhook event
- [ ] Add manual retry endpoint
- [ ] Add tests for webhook job dispatch
- [ ] Add tests for successful webhook delivery
- [ ] Add tests for failed webhook delivery
- [ ] Add tests for fake provider webhook verification
- [ ] Document webhooks in `docs/billing/webhooks.md`

---

## Phase 17 — Queue Integration

- [ ] Verify Redis queue connection
- [ ] Verify queue-worker container
- [ ] Add payment webhook job
- [ ] Add payment activity job if needed
- [ ] Add subscription activation job if needed
- [ ] Add failed job handling
- [ ] Add retry/backoff strategy
- [ ] Add queue tests with fake queue
- [ ] Add docs for queue commands
- [ ] Add docs for queue-worker Docker usage

---

## Phase 18 — Cron / Scheduler

- [ ] Configure Laravel scheduler in Docker if not configured
- [ ] Add scheduled command for expired pending payments
- [ ] Add scheduled command for usage reset
- [ ] Add scheduled command for subscription expiration check
- [ ] Add scheduled command for failed webhook retry if needed
- [ ] Add scheduled command for billing cleanup if needed
- [ ] Add command tests
- [ ] Add scheduler docs
- [ ] Document cron architecture in `docs/billing/scheduler.md`

---

## Phase 19 — Subscription Lifecycle

- [ ] Create subscription in pending state before payment if needed
- [ ] Activate subscription after successful payment
- [ ] Keep subscription inactive after failed payment
- [ ] Handle plan upgrade
- [ ] Handle plan downgrade
- [ ] Handle subscription cancellation
- [ ] Handle subscription expiration
- [ ] Handle subscription renewal simulation
- [ ] Renew subscription from wallet balance if user preference allows
- [ ] Renew subscription by automatic payment method charge if user consent exists
- [ ] Keep subscription past_due if wallet/card renewal fails
- [ ] Add activity log for automatic renewal attempt
- [ ] Create activity logs for subscription changes
- [ ] Add tests for subscription activation
- [ ] Add tests for subscription cancellation
- [ ] Add tests for expired subscription

---

## Phase 20 — Activity Logging

- [ ] Log plan viewed if needed
- [ ] Log subscription created
- [ ] Log subscription activated
- [ ] Log subscription cancelled
- [ ] Log payment created
- [ ] Log payment succeeded
- [ ] Log payment failed
- [ ] Log idempotency replay
- [ ] Log idempotency conflict
- [ ] Log webhook dispatched
- [ ] Log webhook delivered
- [ ] Log webhook failed
- [ ] Log usage limit exceeded
- [ ] Add tests for critical activity logs

---

## Phase 21 — Unified API Response & Errors

- [ ] Review current BaseController/API response format
- [ ] Decide final response contract
- [ ] Ensure billing endpoints use unified success response
- [ ] Ensure billing endpoints use unified error response
- [ ] Add domain exceptions
- [ ] Add payment already processed exception
- [ ] Add invalid payment state exception
- [ ] Add idempotency conflict exception
- [ ] Add subscription inactive exception
- [ ] Add feature limit exceeded exception
- [ ] Add insufficient wallet balance exception
- [ ] Add payment method not found exception
- [ ] Add payment method not allowed exception
- [ ] Add payment preference invalid exception
- [ ] Add duplicate wallet debit exception
- [ ] Add auto charge consent required exception
- [ ] Add provider not configured exception
- [ ] Add provider disabled exception
- [ ] Add provider credentials invalid exception
- [ ] Add provider account not found exception
- [ ] Add provider account forbidden exception
- [ ] Add provider charge failed exception
- [ ] Add provider timeout exception
- [ ] Add provider webhook signature invalid exception
- [ ] Add provider unsupported operation exception
- [ ] Add tests for API errors
- [ ] Document error responses

---

## Phase 22 — API Documentation

- [ ] Update main README with billing module description
- [ ] Add `docs/billing/overview.md`
- [ ] Add `docs/billing/api.md`
- [ ] Add `docs/billing/plans.md`
- [ ] Add `docs/billing/idempotency.md`
- [ ] Add `docs/billing/webhooks.md`
- [ ] Add `docs/billing/scheduler.md`
- [ ] Add `docs/billing/testing.md`
- [ ] Add curl examples
- [ ] Add example payment flow
- [ ] Add example subscription flow
- [ ] Add example chat limit flow
- [ ] Add future dialer billing notes
- [ ] Add wallet balance API examples
- [ ] Add wallet top-up API examples
- [ ] Add wallet payment API examples
- [ ] Add card/payment method API examples
- [ ] Add wallet-first fallback API examples
- [ ] Add payment preferences API examples
- [ ] Add auto-charge consent API examples
- [ ] Add provider abstraction documentation
- [ ] Add simulator provider examples
- [ ] Add platform `.env` provider config examples
- [ ] Add customer DB provider config examples
- [ ] Add provider account admin form examples
- [ ] Add encrypted credentials documentation
- [ ] Add planned Stripe integration notes
- [ ] Add planned PayPal integration notes
- [ ] Add planned LiqPay integration notes
- [ ] Add planned WayForPay integration notes
- [ ] Add provider webhook verification examples
- [ ] Add provider error mapping examples
- [ ] Add provider adapter template documentation

---

## Phase 23 — Testing Strategy

- [ ] Add feature tests for plans API
- [ ] Add feature tests for current subscription API
- [ ] Add feature tests for subscription creation
- [ ] Add feature tests for payment creation
- [ ] Add feature tests for payment success
- [ ] Add feature tests for payment failure
- [ ] Add feature tests for transaction history
- [ ] Add feature tests for webhooks
- [ ] Add feature tests for idempotency
- [ ] Add feature tests for paid chat limits
- [ ] Add feature tests for wallet balance API
- [ ] Add feature tests for wallet top-up API
- [ ] Add feature tests for wallet debit API
- [ ] Add feature tests for payment methods API
- [ ] Add feature tests for payment preferences API
- [ ] Add feature tests for wallet-only payment strategy
- [ ] Add feature tests for card-only payment strategy
- [ ] Add feature tests for wallet-first fallback strategy
- [ ] Add idempotency tests for wallet debit
- [ ] Add idempotency tests for payment method charge
- [ ] Add unit tests for services
- [ ] Add unit tests for DTOs
- [ ] Add unit tests for webhook payload builder
- [ ] Add unit tests for `PaymentProviderInterface` implementations
- [ ] Add unit tests for provider factory
- [ ] Add unit tests for provider config resolver
- [ ] Add unit tests for env-based provider configuration
- [ ] Add unit tests for DB-based provider configuration
- [ ] Add unit tests for encrypted credential masking
- [ ] Add unit tests for provider response mapping
- [ ] Add unit tests for provider error mapping
- [ ] Add feature tests for simulator provider charge flow
- [ ] Add feature tests for simulator provider webhook verification
- [ ] Add tests that real providers are disabled in demo mode
- [ ] Add tests that no real provider secrets are required for local setup
- [ ] Add tests that customer provider credentials are isolated
- [ ] Add tests that one customer cannot use another customer's provider account
- [ ] Add command tests for scheduler tasks
- [ ] Add queue fake tests
- [ ] Add HTTP fake tests
- [ ] Add test docs

---

## Phase 24 — Docker & DevOps Polish

- [ ] Verify backend container
- [ ] Verify nginx container
- [ ] Verify mysql container
- [ ] Verify redis container
- [ ] Verify queue-worker container
- [ ] Add scheduler container or cron strategy if needed
- [ ] Review old `saas_*` names
- [ ] Decide whether to rename containers
- [ ] Keep frontend containers untouched unless needed
- [ ] Document Docker commands
- [ ] Document queue worker commands
- [ ] Document scheduler commands
- [ ] Add troubleshooting docs

---

## Phase 25 — Portfolio Polish

- [ ] Add README section: SaaS Billing Module
- [ ] Add README section: Payment Gateway Simulator
- [ ] Add README section: Idempotency
- [ ] Add README section: Webhooks
- [ ] Add README section: Queue & Scheduler
- [ ] Add README section: Paid Chat Features
- [ ] Add README section: Future Dialer Billing
- [ ] Add architecture diagram text
- [ ] Add interview talking points
- [ ] Add examples of senior-level decisions
- [ ] Explain why repository layer is not used initially
- [ ] Explain transaction boundaries
- [ ] Explain webhook retry strategy
- [ ] Explain idempotency strategy
