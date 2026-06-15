# Scheduler Operations

## Purpose

The scheduler handles periodic billing/runtime maintenance that should not run inside request flow.

## Current Strategy

This repository includes a dedicated `scheduler` service in `docker-compose.yml`.

It runs `php artisan schedule:work` through the scheduler entrypoint.

## Inspect Schedule

```bash
docker compose exec -T backend php artisan schedule:list
```

## Scheduled Billing Tasks

- expire pending payments
- retry due webhooks
- reset usage counters
- check subscription expiration
- clean safe runtime data

## Safe Runtime Notes

- Do not run `schedule:run` if it may trigger state changes you have not reviewed.
- Scheduled commands are designed to be idempotent or guarded with locks.
- The scheduler must not replace queue workers or realtime services.

## Production Guidance

For production, use one of these approaches:

- a cron entry that calls `php artisan schedule:run` every minute
- a dedicated scheduler container
- a managed process supervisor such as Supervisor or systemd

## Billing References

- `docs/billing/scheduler.md`
- `docs/billing/activity-logging.md`
- `docs/billing/webhooks.md`
