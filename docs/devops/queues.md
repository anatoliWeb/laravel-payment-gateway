# Queue Operations

## Purpose

This project uses queues for billing webhooks, cleanup, retries, notifications, and other asynchronous side effects.

## Active Processing Modes

- `queue-worker` is the default runtime worker in Docker development
- `horizon` is available as an optional monitoring profile

Do not run both as active processors for the same queues unless you intentionally want parallel processing.

## Check Worker State

```bash
docker compose ps queue-worker
docker compose logs -f queue-worker
```

## Check Failed Jobs

```bash
docker compose exec -T backend php artisan queue:failed
```

## Retry Failed Jobs

```bash
docker compose exec -T backend php artisan queue:retry all
```

## Horizon Monitoring

```bash
docker compose up -d horizon
docker compose exec -T backend php artisan horizon:status
```

If Horizon is not enabled, `horizon:status` can report inactive. That is expected in the default queue-worker setup.

## Billing Queue Responsibilities

- webhook delivery jobs
- webhook retry dispatch
- payment-related post-event jobs
- audit/logging jobs
- cleanup or maintenance jobs that are intentionally asynchronous

## Notes

- Failed jobs should be retried only after investigating the root cause.
- Queue commands are part of runtime operations, not regular feature validation.
- Avoid `queue:flush` or other destructive queue commands unless you explicitly want to clear failed payloads.
