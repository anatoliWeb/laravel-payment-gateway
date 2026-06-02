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

- [ ] Design user billing blacklist
- [ ] Design user payment blacklist
- [ ] Design subscription-level feature overrides
- [ ] Design user-level feature overrides if needed
- [ ] Decide override priority over plan features
- [ ] Add support for manual feature limit override
- [ ] Add support for temporary feature override
- [ ] Add support for override expiration
- [ ] Add blocked billing access reason
- [ ] Add blocked payment creation reason
- [ ] Add admin/manual override notes
- [ ] Add activity logs for billing restrictions
- [ ] Add activity logs for manual overrides
- [ ] Add tests for billing blacklist
- [ ] Add tests for feature overrides
- [ ] Document overrides in `docs/billing/overrides.md`

Billing overrides must remain module-agnostic. Chat and future dialer modules should use FeatureAccessService and must not implement their own billing override logic.

---

## Phase 11 — Paid Chat Features

- [ ] Define free chat limits
- [ ] Define paid chat limits
- [ ] Define chat messages per day limit
- [ ] Define chat webhooks limit
- [ ] Define chat history retention limit
- [ ] Define chat attachments limit if needed
- [ ] Add feature checks before premium chat actions
- [ ] Add usage increment after billable chat actions
- [ ] Add API error for limit exceeded
- [ ] Add activity log for limit exceeded
- [ ] Add tests for free plan chat limits
- [ ] Add tests for paid plan chat access
- [ ] Add tests for usage tracking

---

## Phase 12 — Future Dialer Billing Foundation

- [ ] Define reusable billing feature names for dialer
- [ ] Define future `dialer.calls.monthly`
- [ ] Define future `dialer.recordings.storage`
- [ ] Define future `dialer.concurrent_calls`
- [ ] Define future `dialer.webhooks`
- [ ] Ensure billing system is not chat-only
- [ ] Ensure feature access works for any module
- [ ] Document future dialer billing extension in `docs/billing/future-dialer.md`

---

## Phase 12.1 — Currency & Exchange Rates Foundation

- [ ] Design `currencies` table
- [ ] Design `exchange_rates` table
- [ ] Define base system currency
- [ ] Define currency code format
- [ ] Define currency display name
- [ ] Define currency symbol
- [ ] Define currency decimal precision
- [ ] Define currency active/inactive state
- [ ] Define currency comment/description field
- [ ] Define exchange rate source
- [ ] Define manual exchange rate mode
- [ ] Define exchange rate validity period
- [ ] Define currency conversion rules
- [ ] Define rounding rules
- [ ] Add currency seeders
- [ ] Add tests for currencies
- [ ] Add tests for exchange rates
- [ ] Document currencies in `docs/billing/currencies.md`

Currency support is required before wallet balances and multi-currency payments. Exchange rates in this portfolio project can be manual/simulated and must not depend on real external providers.

---

## Phase 12.2 — User Wallet Balance

- [ ] Design `wallets` table
- [ ] Design `wallet_balances` table if needed
- [ ] Design `wallet_transactions` table
- [ ] Decide one wallet per user vs one wallet per currency
- [ ] Add multi-currency balance support
- [ ] Add available balance
- [ ] Add held/reserved balance
- [ ] Add wallet transaction types
- [ ] Add wallet top-up transaction
- [ ] Add wallet debit transaction
- [ ] Add wallet refund transaction
- [ ] Add wallet adjustment transaction
- [ ] Add balance locking strategy
- [ ] Add idempotency for wallet transactions
- [ ] Add relation between wallet transactions and payments
- [ ] Add relation between wallet transactions and subscriptions if needed
- [ ] Add wallet activity logs
- [ ] Add tests for wallet balance operations
- [ ] Add tests for multi-currency wallet balances
- [ ] Document wallet balance in `docs/billing/wallets.md`

Users may pay either from internal wallet balance or directly through a payment method. Wallet debits must be transactional and idempotent to avoid double-charging or granting access without a valid debit.

---

## Phase 13 — Payment Creation Flow

- [ ] Create `CreatePaymentRequest`
- [ ] Create `CreatePaymentData` DTO
- [ ] Create `PaymentService`
- [ ] Add payment creation method
- [ ] Validate subscription/payment context
- [ ] Validate amount
- [ ] Validate currency
- [ ] Validate idempotency key
- [ ] Support payment source: direct payment method
- [ ] Support payment source: internal wallet balance
- [ ] Support payment source: wallet first with payment method fallback
- [ ] Validate user payment preference
- [ ] Validate wallet balance before wallet debit
- [ ] Create payment inside DB transaction
- [ ] Create wallet debit transaction when paying from balance
- [ ] Do not activate subscription if wallet debit fails
- [ ] Link payment to wallet transaction if balance is used
- [ ] Create initial payment transaction record
- [ ] Create activity log record
- [ ] Return unified API response
- [ ] Add feature tests for payment creation
- [ ] Add validation tests

---

## Phase 13.1 — Payment Risk & Fraud Guard

- [ ] Define payment risk rules
- [ ] Add payment risk check before payment creation
- [ ] Add user payment blacklist check
- [ ] Add max failed payment attempts per period
- [ ] Add max payment creation attempts per hour/day
- [ ] Add suspicious payment activity flags
- [ ] Add blocked payment reason
- [ ] Add payment risk metadata
- [ ] Add activity log for blocked payments
- [ ] Add activity log for suspicious attempts
- [ ] Ensure risk guard does not replace idempotency
- [ ] Ensure idempotency still prevents duplicate payment creation
- [ ] Add tests for payment blacklist
- [ ] Add tests for failed-attempt limits
- [ ] Add tests for suspicious activity blocking
- [ ] Document payment risk guard in `docs/billing/payment-risk.md`

Payment Risk & Fraud Guard is a demo-safe risk layer for the simulator. It is not a real bank-grade antifraud system.

---

## Phase 13.2 — Auto Top-Up & Auto Charge

- [ ] Design user payment method preference
- [ ] Design auto top-up settings
- [ ] Design auto charge settings
- [ ] Allow user to choose wallet balance only
- [ ] Allow user to choose card/payment method only
- [ ] Allow user to choose wallet first, then card fallback
- [ ] Allow user to enable/disable automatic charges
- [ ] Add minimum wallet balance threshold
- [ ] Add auto top-up amount
- [ ] Add max auto top-up per day/month
- [ ] Add failed auto top-up handling
- [ ] Add auto charge consent tracking
- [ ] Add activity log for auto charge consent changes
- [ ] Add activity log for automatic balance top-up
- [ ] Add activity log for automatic subscription charge
- [ ] Add tests for auto top-up disabled
- [ ] Add tests for auto top-up enabled
- [ ] Add tests for wallet-first payment strategy
- [ ] Add tests for card-only payment strategy
- [ ] Add tests for max auto top-up limits
- [ ] Document auto top-up in `docs/billing/auto-top-up.md`

Auto top-up and auto charge require explicit user consent. In this simulator project, external payment provider behavior is fake, but consent, limits, idempotency, and audit logging must be modeled seriously.

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
- [ ] Prevent duplicate auto top-up
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
- [ ] Add manual retry endpoint
- [ ] Add tests for webhook job dispatch
- [ ] Add tests for successful webhook delivery
- [ ] Add tests for failed webhook delivery
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
- [ ] Add unit tests for services
- [ ] Add unit tests for DTOs
- [ ] Add unit tests for webhook payload builder
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
