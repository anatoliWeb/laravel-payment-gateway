# Billing Domain Architecture

## Purpose

Define the target domain structure for Billing and Payments modules before writing implementation code.  
This phase documents boundaries, namespace plan, dependency direction, and integration conventions.

## Design Principles

- Keep controllers thin and service-driven.
- Separate Billing domain concerns from Payments domain concerns.
- Use DTO + FormRequest + Resource patterns consistently.
- Keep module boundaries reusable for chat billing now and dialer billing later.
- Prefer explicit dependencies and avoid circular coupling.
- Reuse existing shared platform capabilities (RBAC, ActivityLog, Queue, API response envelope).

## Module Boundaries

Billing module owns:
- plans
- plan features
- subscriptions
- feature access decisions
- usage tracking/limits
- subscription lifecycle
- future invoice coordination
- billing scheduler orchestration

Payments module owns:
- payment attempts
- payment simulator behavior
- payment status transitions
- payment transaction history
- idempotency handling
- webhook deliveries/retries
- payment-focused scheduler orchestration

Boundary rules:
- Billing may call Payments for payment creation/tracking.
- Payments must not depend on chat internals.
- Chat depends on Billing feature-access abstractions, not payment internals.
- Future Dialer reuses Billing usage/access model instead of duplicating it.

## Target Namespace Map

Target plan only (no folders/classes created in this phase):

```text
app/
|-- Services/
|   |-- Billing/
|   `-- Payments/
|-- DTO/
|   |-- Billing/
|   `-- Payments/
|-- Enums/
|   |-- Billing/
|   `-- Payments/
|-- Http/
|   |-- Requests/Api/V1/Billing/
|   |-- Requests/Api/V1/Payments/
|   `-- Resources/
|       |-- Billing/
|       `-- Payments/
|-- Jobs/
|   |-- Billing/
|   `-- Payments/
`-- Exceptions/
    |-- Billing/
    `-- Payments/
```

## app/Services/Billing

Planned services:
- `PlanService`: plan discovery/filtering and availability decisions.
- `SubscriptionService`: subscription create/change/cancel orchestration.
- `FeatureAccessService`: resolve allow/deny for feature keys.
- `FeatureUsageService`: usage read/update/reset orchestration.
- `UsageLimitService`: limit policy evaluation and threshold decisions.
- `BillingCycleService`: period/cycle window calculations.
- `InvoiceService` (future): invoice lifecycle coordination.
- `BillingSchedulerService` (optional): command-level orchestration helpers.
- `BillingActivityService` (optional): adapter around existing ActivityLog service.

Dependency guidance:
- Should depend on DTOs, enums, models, and existing shared infra services.
- Should not depend on HTTP layer objects directly.
- Should not depend on chat internals.

Manual restrictions and feature overrides are documented in [Billing Overrides & Restrictions](./overrides.md).

## app/Services/Payments

Planned services:
- `PaymentService`: payment attempt creation coordinator.
- `PaymentSimulationService`: success/failure simulation orchestration.
- `PaymentStatusTransitionService`: legal transition enforcement.
- `PaymentTransactionService`: append-only timeline writes.
- `IdempotencyService`: replay/conflict guard for create/retry.
- `WebhookDeliveryService`: delivery state + scheduling coordination.
- `WebhookPayloadBuilder`: payload shaping/sanitization.
- `PaymentRetryService`: new-attempt creation flow.
- `PaymentExpirationService`: expiration policy execution.
- `PaymentMetadataSanitizer`: metadata safety/filtering.

Dependency guidance:
- Should depend on DTOs, enums, models, queue abstractions, shared infra services.
- Should not depend on chat module internals.
- Should expose clear interfaces to Billing orchestration.

## app/DTO/Billing

Planned DTOs:
- `CreateSubscriptionData`
- `ChangePlanData`
- `CancelSubscriptionData`
- `FeatureAccessCheckData`
- `FeatureAccessResultData`
- `UsageIncrementData`
- `UsageResetData`
- `PlanFeatureData`
- `BillingContextData`

DTO characteristics:
- immutable-like data carriers
- typed fields
- no Eloquent/business logic

## app/DTO/Payments

Planned DTOs:
- `CreatePaymentData`
- `PaymentResponseData`
- `SimulatePaymentSuccessData`
- `SimulatePaymentFailureData`
- `RetryPaymentData`
- `WebhookPayloadData`
- `WebhookDeliveryResultData`
- `IdempotencyContextData`
- `PaymentMetadataData`

DTO characteristics:
- explicit request/response/service payload boundaries
- no persistence logic

## app/Enums/Billing

Planned enums:
- `PlanType`
- `BillingInterval`
- `SubscriptionStatus`
- `BillingFeature`
- `FeatureValueType`
- `UsagePeriod`
- `ResetPolicy`
- `SubscriptionCancellationMode`

Rule:
- PHP enums mapped to string DB columns (avoid rigid DB ENUM).

## app/Enums/Payments

Planned enums:
- `PaymentStatus`
- `PaymentMethod`
- `PaymentProvider`
- `PaymentTransactionType`
- `IdempotencyStatus`
- `WebhookDeliveryStatus`
- `PaymentFailureReason`
- `WebhookEventType`

Rule:
- explicit enum-driven state modeling with stable values.

## app/Http/Requests/Api/V1/Billing

Planned FormRequests:
- `CreateSubscriptionRequest`
- `ChangePlanRequest`
- `CancelSubscriptionRequest`
- `GetUsageRequest` (if filterable endpoint needed)
- `CheckFeatureAccessRequest` (if endpoint exposed)

Responsibility:
- input shape validation only, not business-state decisions.

## app/Http/Requests/Api/V1/Payments

Planned FormRequests:
- `CreatePaymentRequest`
- `SimulatePaymentSuccessRequest`
- `SimulatePaymentFailureRequest`
- `RetryPaymentRequest`
- `RetryWebhookDeliveryRequest`

Responsibility:
- request payload/header validation (including idempotency header presence where required).
- state transition validity remains service-layer concern.

## app/Http/Resources/Billing

Planned resources:
- `PlanResource`
- `PlanFeatureResource`
- `SubscriptionResource`
- `FeatureUsageResource`
- `BillingUsageSummaryResource`

Output policy:
- safe, minimal, client-oriented fields
- no internal-only sensitive/operational fields

## app/Http/Resources/Payments

Planned resources:
- `PaymentResource`
- `PaymentStatusResource`
- `PaymentTransactionResource`
- `WebhookDeliveryResource`
- `IdempotencyReplayResource` (if needed)

Output policy:
- expose public IDs over raw DB IDs where practical
- never expose secrets/raw sensitive callback data

## app/Jobs/Billing

Planned jobs:
- `ResetFeatureUsageJob`
- `ExpireSubscriptionsJob` (or command + service orchestration)
- `GenerateRecurringInvoiceJob` (future)
- `SendBillingReminderJob` (future)

Notes:
- some responsibilities may be command-driven orchestration calling services.

## app/Jobs/Payments

Planned jobs:
- `SendPaymentWebhookJob`
- `RetryFailedWebhookDeliveryJob`
- `ExpirePendingPaymentJob` (or batch command orchestration)
- `ProcessPaymentSideEffectsJob` (if needed)

Notes:
- callback HTTP must remain async (not request-cycle).
- retry/backoff behavior explicit per job policy.

## app/Exceptions/Billing

Planned exceptions:
- `SubscriptionNotFoundException`
- `SubscriptionInactiveException`
- `FeatureNotAvailableException`
- `FeatureLimitExceededException`
- `InvalidPlanChangeException`
- `PlanNotAvailableException`

Policy:
- map to stable API error codes/messages.
- no internal stack details in client responses.

## app/Exceptions/Payments

Planned exceptions:
- `PaymentNotFoundException`
- `PaymentAlreadyFinalizedException`
- `InvalidPaymentStateException`
- `IdempotencyKeyMissingException`
- `IdempotencyConflictException`
- `WebhookDeliveryNotFoundException`
- `WebhookRetryNotAllowedException`
- `PaymentRetryNotAllowedException`

Policy:
- map to unified API envelope with stable codes.
- avoid raw provider/internal leakage.

## Controllers Placement

Planned target controllers (not created now):
- `Api/V1/Billing/BillingPlanController`
- `Api/V1/Billing/SubscriptionController`
- `Api/V1/Billing/UsageController`
- `Api/V1/Payments/PaymentController`
- `Api/V1/Payments/PaymentSimulationController`
- `Api/V1/Payments/PaymentTransactionController`
- `Api/V1/Payments/WebhookDeliveryController`

Flow shape:
- Controller -> FormRequest -> DTO -> Service -> Resource/API response.

## Models Placement

Planned models (not created now):
- `Plan`
- `PlanFeature`
- `Subscription`
- `FeatureUsage`
- `Payment`
- `PaymentTransaction`
- `IdempotencyKey`
- `WebhookDelivery`

Placement policy:
- keep in `app/Models` for consistency unless future domain-folder refactor is justified.

## Events / Listeners Placement

Planned billing events:
- `SubscriptionCreated`
- `SubscriptionActivated`
- `SubscriptionCancelled`
- `PlanChanged`
- `FeatureLimitExceeded`

Planned payment events:
- `PaymentCreated`
- `PaymentSucceeded`
- `PaymentFailed`
- `PaymentExpired`
- `WebhookDelivered`
- `WebhookFailed`

Usage:
- trigger activity logging, notifications, async side effects.
- prefer `afterCommit` listeners where persisted state consistency matters.

## Policies / Authorization

- Billing/payment endpoints require authentication.
- Users can access own subscription/payment data.
- Admin/system roles can access broader billing operations.

Planned permission keys:
- `billing.plans.view`
- `billing.subscriptions.view`
- `billing.subscriptions.manage`
- `billing.payments.view`
- `billing.payments.manage`
- `billing.webhooks.view`
- `billing.webhooks.retry`

## Activity Logging Integration

- Reuse existing ActivityLog integration pattern/service.
- Log high-signal billing/payment lifecycle events.
- Avoid noisy per-action logs when they add little operational value.
- Always sanitize metadata.

## Queue / Scheduler Integration

- Async jobs for webhook callbacks and billing notifications.
- Scheduler commands orchestrate services (idempotent by design).
- Keep command handlers orchestration-focused.

Planned commands:
- `billing:expire-pending-payments`
- `billing:reset-usage`
- `billing:retry-webhooks`
- `billing:cleanup-idempotency`
- `billing:expire-subscriptions`

## API Response Integration

- Follow existing unified API response envelope.
- Preserve standardized validation error shape.
- Use stable business error codes for domain exceptions.

## Testing Structure

Target test layout:
- `tests/Feature/Billing/`
- `tests/Feature/Payments/`
- `tests/Unit/Services/Billing/`
- `tests/Unit/Services/Payments/`
- `tests/Unit/DTO/Billing/`
- `tests/Unit/DTO/Payments/`

Coverage focus:
- feature tests for API flows
- unit tests for transitions/idempotency/DTO/payload builders
- queue fake tests for async callbacks
- scheduler command orchestration tests

## Dependency Direction Rules

Main rules:
- Controllers depend on Requests/DTO/Services/Resources.
- Services depend on Models/DTO/Enums/shared infra.
- DTO/Enums do not depend on Eloquent.
- Jobs depend on Services/DTO and infra abstractions.
- Chat/Dialer consume Billing feature-access abstraction.
- Payments do not depend on Chat/Dialer internals.
- Billing may depend on Payments through clear service boundary.

Circular dependency prevention:
- no mutual service calls without explicit orchestration boundary
- isolate cross-domain calls via interface/contract where needed

## Naming Rules

- Use `Billing` namespace for plans/subscriptions/usage concerns.
- Use `Payments` namespace for payment/idempotency/webhook concerns.
- Service suffix: `*Service`
- DTO suffix: `*Data`
- Request suffix: `*Request`
- Resource suffix: `*Resource`
- Job suffix: `*Job`
- Exception suffix: `*Exception`
- Enums named by singular domain concept.

## Non-Goals For This Phase

- No physical folders/classes created.
- No runtime logic implementation.
- No route/controller/service/model/request/resource/job/enum/exception code creation.

## Implementation Notes For Next Phases

- Next phase should finalize API contract planning using these boundaries.
- After API planning, implementation phases should create folders/classes incrementally by vertical slice.
- Preserve thin-controller/service-centric architecture from first implementation commit.

API contract planning details: [Billing API Contract](./api.md).
Enum/status planning details: [Enums & Statuses Planning](./statuses.md).

## Status

- Phase 5 is architecture/documentation only.
- No folders/classes have been created yet.
- No billing/payment logic has been implemented yet.
- Next phase: API Contract Planning.
