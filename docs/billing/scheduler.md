# Cron / Scheduler

## Purpose

Phase 18 adds the billing scheduler foundation for recurring runtime maintenance:
- expire stale pending/processing simulator payments
- reset due feature usage counters
- check elapsed subscription periods
- queue due webhook retries
- clean safe non-ledger runtime data

The scheduler is operational glue. It coordinates existing persistence and queue boundaries; it does not replace event-driven payment actions.

## Non-Goals

- No real provider charges.
- No subscription renewal.
- No subscription activation.
- No wallet/card auto-charge.
- No PDF, SMS, or email provider work.
- No deletion of payments, invoices, wallet transactions, or payment transactions.
- No reports UI/API.

## Docker Scheduler Service

`docker-compose.yml` defines a dedicated `scheduler` service:
- image/context: backend PHP image
- working directory: `/var/www`
- command: `sh /var/www/docker/scheduler/entrypoint.sh`
- runtime command: `php artisan schedule:work`
- dependencies: backend, mysql, redis
- volume: `./backend:/var/www`

The scheduler does not run php-fpm, Horizon, Reverb, or queue worker logic.

## Scheduled Commands

Registered commands:
- `billing:expire-pending-payments`
- `billing:reset-usage`
- `billing:check-subscription-expiration`
- `billing:retry-webhooks`
- `billing:cleanup`

Frequencies:
- payment expiration: every five minutes
- webhook retry: every minute
- usage reset: hourly
- subscription expiration check: hourly
- cleanup: daily at 03:30

All scheduled entries use `withoutOverlapping()`. `onOneServer()` is intentionally not enabled until the project defines an explicit distributed cache lock policy.

## Payment Expiration

`billing:expire-pending-payments` finds stale `pending` and `processing` payments using a TTL from `created_at` because the `payments` table does not currently have an `expires_at` column.

Safety rules:
- only simulator-safe providers are processed: `simulator`, `manual`, `internal_wallet`
- final payments are skipped
- each payment is locked before mutation
- status becomes `expired`
- `expired_at` is set
- one `payment_expired` transaction is appended
- `PaymentExpired` domain event is dispatched
- no subscription is activated
- no external provider is called

## Usage Reset

`billing:reset-usage` resets due `feature_usages` rows for:
- `daily`
- `monthly`
- `billing_cycle`

`lifetime` usage is never reset. Rows are updated in place so usage ownership and audit context remain available.

## Subscription Expiration Check

`billing:check-subscription-expiration` delegates subscription decisions to the Phase 19 lifecycle service.

It can:
- expire elapsed subscriptions when no renewal is configured
- attempt wallet renewal when user preferences and balance allow it
- create simulator-safe automatic payment attempts when auto-charge consent exists
- move failed renewal attempts to `past_due`
- leave already `past_due` subscriptions recoverable

Full lifecycle behavior is documented in [Subscription Lifecycle](./subscription-lifecycle.md).

## Webhook Retry

`billing:retry-webhooks` queues due billing webhook deliveries.

Eligible scheduler statuses:
- `pending`
- `failed`
- `retrying`

Queued deliveries are processed by `SendWebhookDeliveryJob`; the scheduler does not re-queue them.

Skipped statuses:
- `delivered`
- `permanently_failed`

The command respects `attempts < max_attempts` and `next_retry_at`. It marks due deliveries as `queued` before dispatching `SendWebhookDeliveryJob`, preventing overlapping scheduler ticks from enqueueing duplicate jobs.

## Billing Cleanup

`billing:cleanup` cleans safe non-ledger runtime data only:
- expired idempotency records after retention
- old stored webhook response bodies after retention

It never deletes:
- payments
- invoices
- wallet transactions
- payment transactions
- financial ledger records

The command supports `--dry-run` for retention review.

## Locking and Idempotency

Commands use row-level locking before financial or retry-state mutations. Repeated command runs are idempotent because completed rows no longer match due criteria:
- expired payments are final and skipped
- reset usage rows have `used = 0`
- queued webhook deliveries are removed from the due scheduler query
- expired subscriptions no longer match expirable statuses

## Activity Logging

Activity actions:
- `billing.scheduler.expire_pending_payments`
- `billing.scheduler.reset_usage`
- `billing.scheduler.retry_webhooks`
- `billing.scheduler.cleanup`
- `billing.scheduler.subscription_expiration_check`

Logs stay compact and summarize operational outcomes. Secrets, raw card data, and provider credentials are not logged.

## Operational Notes

Use:
- `php artisan schedule:list` to inspect registration
- `php artisan list` to inspect billing commands
- `docker compose config` to validate Docker service configuration

The scheduler container should run continuously in Docker development. Queue workers remain responsible for asynchronous job execution.

## Testing Strategy

Targeted tests cover:
- payment expiration transition and duplicate prevention
- usage reset behavior
- subscription expiration foundation
- webhook retry dispatch
- cleanup dry-run and retention
- scheduler registration
- existing webhook, payment simulation, idempotency, invoice, and billing event regression

## Status

Phase 18 scheduler foundation is implemented. Subscription renewal, provider charges, and advanced lifecycle automation remain future phases.
