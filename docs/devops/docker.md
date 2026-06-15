# Docker Operations

## Purpose

This project is designed to run in Docker for local development, validation, and portfolio demos.

## Core Services

- `backend` - Laravel API and artisan runtime
- `frontend` - Angular frontend
- `vue-frontend` - Vite-based frontend runtime
- `mysql` - application database
- `redis` - cache, queue, and runtime coordination
- `nginx` - HTTP entrypoint
- `queue-worker` - asynchronous job worker
- `scheduler` - scheduled billing/runtime jobs
- `reverb` - realtime broadcast server
- `horizon` - optional queue monitoring profile

## Start the Stack

```bash
docker compose up -d
```

## Check Configuration

```bash
docker compose config
```

## Check Runtime State

```bash
docker compose ps
```

## View Logs

```bash
docker compose logs -f backend
docker compose logs -f nginx
docker compose logs -f queue-worker
docker compose logs -f scheduler
docker compose logs -f reverb
```

## Exec Into Containers

```bash
docker compose exec -T backend php artisan --version
docker compose exec -T backend php artisan about
docker compose exec -T nginx nginx -t
```

## Safe Shutdown

```bash
docker compose stop
```

Use `docker compose down` only when you intentionally want to stop the stack and remove containers. Do not use `docker compose down -v` during routine development because it removes volumes.

## Notes

- The stack currently uses `payment_gateway_*` container names.
- Old `saas_*` references were reviewed and should not remain in active compose/service definitions.
- Docker validation should prefer targeted service checks over rebuilds.
