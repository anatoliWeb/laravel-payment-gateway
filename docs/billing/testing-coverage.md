# Billing Testing Coverage

This map is a living audit for Phase 23. It records which requested test areas are already covered, which were added during the audit, and which remain future work because the underlying feature does not exist yet.

## Coverage Map

| Phase 23 item | Status | Test file(s) | Notes |
|---|---|---|---|
| Add feature tests for plans API | future | `backend/tests/Feature/Billing/PlanAccessServiceTest.php` | Plan access logic is covered, but there is no dedicated plans API route/controller yet. |
| Add feature tests for current subscription API | partial | `backend/tests/Feature/Billing/SubscriptionApiTest.php`, `backend/tests/Feature/Billing/PlanAccessServiceTest.php` | Subscription create/cancel and current-subscription service behavior are covered; a dedicated current-subscription API endpoint is not present. |
| Add feature tests for subscription creation | covered | `backend/tests/Feature/Billing/SubscriptionApiTest.php` | Creates a subscription and payment attempt. |
| Add feature tests for payment creation | covered | `backend/tests/Feature/Billing/PaymentCreationFlowTest.php` | Covers wallet, card, wallet-first, validation, and ownership cases. |
| Add feature tests for payment success | covered | `backend/tests/Feature/Billing/PaymentSimulationFlowTest.php` | Success simulation updates payment state and timeline. |
| Add feature tests for payment failure | covered | `backend/tests/Feature/Billing/PaymentSimulationFlowTest.php` | Failure simulation updates payment state and timeline. |
| Add feature tests for transaction history | covered | `backend/tests/Feature/Billing/PaymentCreationFlowTest.php`, `backend/tests/Feature/Billing/AdminBillingApiTest.php` | Payment transaction history is covered for user/admin read surfaces. |
| Add feature tests for webhooks | covered | `backend/tests/Feature/Billing/WebhookDeliveryFlowTest.php`, `backend/tests/Feature/Billing/WebhookRetryApiTest.php` | Queue fake and HTTP fake coverage exists for delivery/retry behavior. |
| Add feature tests for idempotency | covered | `backend/tests/Feature/Billing/PaymentIdempotencyTest.php`, `backend/tests/Feature/Billing/WalletTopUpIdempotencyTest.php`, `backend/tests/Feature/Billing/WalletAdjustmentIdempotencyTest.php` | Replay and conflict behavior are covered. |
| Add feature tests for paid chat limits | covered | `backend/tests/Feature/Billing/PaidChatFeaturesTest.php`, `backend/tests/Feature/Billing/UsageLimitServiceTest.php` | Usage gating and plan limits are covered at service/test level. |
| Add feature tests for wallet balance API | covered | `backend/tests/Feature/Billing/WalletApiTest.php` | Wallet balances and wallet transaction list are covered. |
| Add feature tests for wallet top-up API | covered | `backend/tests/Feature/Billing/WalletApiTest.php`, `backend/tests/Feature/Billing/WalletTopUpIdempotencyTest.php` | Top-up flow and idempotency are covered. |
| Add feature tests for wallet debit API | covered | `backend/tests/Feature/Billing/PaymentCreationFlowTest.php`, `backend/tests/Feature/Billing/WalletAdjustmentApiTest.php` | Debit behavior is covered through wallet payment and wallet adjustment flows. |
| Add feature tests for payment methods API | covered | `backend/tests/Feature/Billing/PaymentMethodsApiTest.php` | List/create/default/deactivate and ownership validation are covered. |
| Add feature tests for payment preferences API | covered | `backend/tests/Feature/Billing/PaymentPreferencesApiTest.php` | Strategy updates, consent, and auto-top-up validation are covered. |
| Add feature tests for wallet-only payment strategy | covered | `backend/tests/Feature/Billing/PaymentCreationFlowTest.php`, `backend/tests/Feature/Billing/SubscriptionRenewalTest.php` | Wallet-first and wallet-only behaviors are exercised. |
| Add feature tests for card-only payment strategy | covered | `backend/tests/Feature/Billing/PaymentCreationFlowTest.php`, `backend/tests/Feature/Billing/SubscriptionRenewalTest.php` | Payment-method-only behavior is exercised. |
| Add feature tests for wallet-first fallback strategy | covered | `backend/tests/Feature/Billing/PaymentCreationFlowTest.php` | Wallet-first fallback and insufficient-wallet fallback are covered. |
| Add idempotency tests for wallet debit | covered | `backend/tests/Feature/Billing/WalletAdjustmentIdempotencyTest.php` | Duplicate debit/adjustment behavior is covered. |
| Add idempotency tests for payment method charge | covered | `backend/tests/Feature/Billing/PaymentIdempotencyTest.php` | Duplicate payment-method charge behavior is covered. |
| Add unit tests for services | covered | `backend/tests/Feature/Billing/*ServiceTest.php`, `backend/tests/Unit/*` | Service behavior is broad and already exercised. |
| Add unit tests for DTOs | partial | `backend/tests/Feature/Billing/*` | DTOs are indirectly exercised through services and flows; no dedicated DTO-only tests were added in this audit. |
| Add unit tests for webhook payload builder | future | `backend/tests/Feature/Billing/WebhookDeliveryFlowTest.php` | Payload building is still covered through `WebhookDeliveryService`; a separate builder class does not exist yet. |
| Add unit tests for `PaymentProviderInterface` implementations | covered | `backend/tests/Feature/Billing/SimulatorPaymentProviderTest.php`, `backend/tests/Feature/Billing/PaymentProviderFactoryTest.php` | Simulator provider behavior is covered. |
| Add unit tests for provider factory | covered | `backend/tests/Feature/Billing/PaymentProviderFactoryTest.php` | Factory resolution and disabled-provider behavior are covered. |
| Add unit tests for provider config resolver | covered | `backend/tests/Feature/Billing/PaymentProviderConfigResolverTest.php` | Env/database priority and isolation are covered. |
| Add unit tests for env-based provider configuration | covered | `backend/tests/Feature/Billing/PaymentProviderConfigResolverTest.php` | Env config resolves without requiring real secrets. |
| Add unit tests for DB-based provider configuration | covered | `backend/tests/Feature/Billing/PaymentProviderConfigResolverTest.php` | Database provider account priority is covered. |
| Add unit tests for encrypted credential masking | covered | `backend/tests/Feature/Billing/PaymentProviderAccountTest.php` | Credentials are encrypted and masked. |
| Add unit tests for provider response mapping | covered | `backend/tests/Feature/Billing/SimulatorPaymentProviderTest.php` | Charge/refund/status mapping is covered. |
| Add unit tests for provider error mapping | covered | `backend/tests/Feature/Billing/SimulatorPaymentProviderTest.php` | Webhook verification error mapping is covered. |
| Add feature tests for simulator provider charge flow | covered | `backend/tests/Feature/Billing/SimulatorPaymentProviderTest.php`, `backend/tests/Feature/Billing/PaymentCreationFlowTest.php` | Simulator charge behavior is covered. |
| Add feature tests for simulator provider webhook verification | covered | `backend/tests/Feature/Billing/SimulatorPaymentProviderTest.php` | Predictable webhook verification is covered. |
| Add tests that real providers are disabled in demo mode | covered | `backend/tests/Feature/Billing/PaymentProviderFactoryTest.php` | External providers are rejected when disabled. |
| Add tests that no real provider secrets are required for local setup | covered | `backend/tests/Feature/Billing/PaymentProviderConfigResolverTest.php` | Local/demo config resolves safely. |
| Add tests that customer provider credentials are isolated | covered | `backend/tests/Feature/Billing/PaymentProviderConfigResolverTest.php`, `backend/tests/Feature/Billing/PaymentProviderAccountTest.php` | Database account scope is user-isolated. |
| Add tests that one customer cannot use another customer's provider account | covered | `backend/tests/Feature/Billing/PaymentProviderConfigResolverTest.php` | Access is blocked for foreign accounts. |
| Add command tests for scheduler tasks | covered | `backend/tests/Feature/Billing/BillingSchedulerRegistrationTest.php`, `backend/tests/Feature/Billing/BillingRetryWebhooksCommandTest.php`, `backend/tests/Feature/Billing/BillingExpirePendingPaymentsCommandTest.php`, `backend/tests/Feature/Billing/BillingResetUsageCommandTest.php`, `backend/tests/Feature/Billing/BillingCleanupCommandTest.php` | Scheduler registration and core billing commands are covered. |
| Add queue fake tests | covered | `backend/tests/Feature/Billing/WebhookDeliveryFlowTest.php`, `backend/tests/Feature/Billing/BillingRetryWebhooksCommandTest.php`, `backend/tests/Feature/Billing/BillingPaymentEventsTest.php` | Bus fake coverage exists for webhook dispatch and related jobs. |
| Add HTTP fake tests | covered | `backend/tests/Feature/Billing/WebhookDeliveryFlowTest.php` | Webhook delivery jobs use HTTP fakes. |
| Add test docs | covered | `docs/billing/testing.md`, `docs/billing/testing-coverage.md` | Testing policy and coverage matrix are documented. |
| Add backend tests for admin billing APIs | covered | `backend/tests/Feature/Billing/AdminBillingApiTest.php` | Read-only admin surfaces and forbidden-path access are covered. |
| Add backend tests for permissions | covered | `backend/tests/Feature/Billing/BillingRbacSeederTest.php`, `backend/tests/Feature/Billing/AdminBillingApiTest.php` | Seeder permissions and admin gate checks are covered. |
| Add backend tests for demo seeders | covered | `backend/tests/Feature/Billing/BillingDemoSeederTest.php` | Demo data is idempotent and stable. |
| Add frontend tests for updated admin billing UI | covered | `frontend/src/app/features/admin-billing/pages/admin-billing-dashboard/admin-billing-dashboard-page.component.spec.ts` | Dashboard now covers the read-only admin datasets. |
| Add frontend tests for checkout simulation buttons if implemented | covered | `frontend/src/app/features/billing/pages/billing-checkout/billing-checkout-page.component.spec.ts` | Checkout simulation interaction is already covered. |

## Notes

- `RefreshDatabase` still appears in several smaller tests, but billing feature flows that exercise real transactional behavior generally use `DatabaseTransactions`.
- `WebhookPayloadBuilder` is still a future extraction candidate; payload assertions currently live inside the webhook delivery flow tests.
- Plans API coverage is intentionally left future because the endpoint itself is not implemented in the current codebase.
