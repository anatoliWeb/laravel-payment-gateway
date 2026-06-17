# Laravel SaaS Billing Platform with Payment Gateway Simulator

API-first SaaS foundation built as a modular monolith: Laravel backend, Vue Admin (inside backend), Angular dashboard, RBAC, chat/realtime, OpenAPI docs, Docker, and CI/CD/release preparation.

Billing direction for this repository: implemented Billing & Payment Gateway Simulator module on top of the existing SaaS baseline.

## Highlights

- API-first Laravel backend (`/api/v1`)
- Vue Admin + Angular Dashboard split frontend architecture
- RBAC with permission-aware navigation and API access control
- Chat module: conversations, messages, participants, typing, presence, attachments, webhooks, external API
- OpenAPI/Swagger with permission-aware docs portal and filtered spec
- Redis cache/queue foundations and queue worker strategy
- Laravel Reverb realtime foundations
- Dockerized local environment (backend/mysql/redis/nginx/queue/reverb/frontend)
- Security hardening foundations (rate limiting, secure headers, token/validation hardening, realtime auth)
- Monitoring/logging foundations (health endpoints, structured logs, queue/realtime/container logging)
- Modular monolith architecture with documented future microservices strategy

## Tech Stack

### Backend

- PHP 8.3
- Laravel 13
- MySQL 8
- Redis 7
- Laravel Sanctum
- Laravel Reverb
- dedoc/scramble (OpenAPI)

### Frontend

- Vue 3 + Pinia + Vue Router (Admin, via Vite)
- Angular 21 (Dashboard)
- SCSS

### Infrastructure / DevOps

- Docker Compose
- Nginx
- Queue worker + Horizon profile
- GitHub Actions CI

## Architecture Overview

Current architecture is a **modular monolith** with API-first boundaries, service-layer organization, event-driven side effects, and documented extraction strategy for future microservices.

- Architecture details: [backend/docs/architecture.md](backend/docs/architecture.md)
- Future extraction planning: [backend/docs/microservices.md](backend/docs/microservices.md)

## Main Features

### Billing Module

- Implemented Billing & Payment Gateway Simulator module
- Subscription lifecycle with payment creation, simulation, invoices, and webhooks
- Idempotency for write operations and replay-safe billing behavior
- Wallet balance, payment methods, payment preferences, and manual adjustments
- Queue-based webhook delivery and scheduler-driven cleanup/maintenance
- Shared billing feature access for chat and future dialer monetization scenarios

Billing documentation:

- [Billing overview](docs/billing/overview.md)
- [Billing API](docs/billing/api.md)
- [Billing Reports API](docs/billing/reports-api.md)
- [Billing user portal UI](docs/billing/user-portal-ui.md)
- [Billing checkout/payment UI](docs/billing/checkout-payment-ui.md)
- [Billing admin/operator UI](docs/billing/admin-operator-ui.md)
- [Billing reports/analytics UI](docs/billing/reports-ui.md)
- [Billing seller/company UI](docs/billing/seller-company-ui.md)
- [Billing demo flows](docs/billing/demo-flows.md)
- [Billing testing](docs/billing/testing.md)
- [Payment provider abstraction](docs/billing/payment-providers.md)

### Auth & RBAC

- Session-first auth with bearer/token support
- Roles/permissions with middleware enforcement
- Permission-aware docs and admin navigation

### Chat & Realtime

- Direct/group conversations and messaging
- Participant roles/access states/capabilities
- Attachment upload/download policies
- Read/delivery/device-read states
- Typing and presence channels
- Webhook and external API integration
- Safe realtime payload foundations

### API Documentation

- Permission-aware docs portal: `/docs/api/portal`
- User-filtered spec: `/docs/api.filtered.json`
- Raw Swagger UI/spec for full-access users: `/docs/api`, `/docs/api.json`
- OpenAPI generation with Scramble

### Security

- Rate limiting policies
- Secure headers policy
- Validation hardening
- Token security hardening
- Realtime channel authorization hardening
- Docker security review foundations

### Performance

- Redis caching foundations
- Query optimization pass
- Asset optimization pass
- Queue performance optimization

### Monitoring & DevOps

- Public liveness + protected readiness endpoints
- Structured logging policy
- Queue/realtime logging foundations
- Container log strategy
- CI/CD preparation and release workflow preparation

## Local Development

Use repository root as working directory.

```bash
cp backend/.env.example backend/.env
docker compose up -d
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --seed
docker compose exec -T backend sh -lc 'APP_ENV=local BILLING_DEMO_SEED=true php artisan db:seed'
docker compose exec backend npm ci
docker compose exec backend npm run build
```

Angular dashboard (if needed):

```bash
docker compose exec frontend npm ci
docker compose exec frontend npm run build
```

Runtime and operations references:

- [Docker Operations](docs/devops/docker.md)
- [Queue Operations](docs/devops/queues.md)
- [Scheduler Operations](docs/devops/scheduler.md)
- [Troubleshooting](docs/devops/troubleshooting.md)
- [Portfolio Screenshot Plan](docs/portfolio/screenshots.md)

## Useful URLs

Based on default `docker-compose.yml` / `.env` values:

- Backend (Nginx): `http://localhost:8080`
- API base: `http://localhost:8080/api/v1`
- Vue Admin (Vite dev): `http://localhost:5173`
- Angular Dashboard: `http://localhost:4200`
- Billing demo flows: `http://localhost:4200/billing/demo`
- API docs portal: `http://localhost:8080/docs/api/portal`
- Swagger UI (full-access policy): `http://localhost:8080/docs/api`
- Public liveness: `http://localhost:8080/health`

## Testing

Backend:

```bash
docker compose exec backend php artisan test
docker compose exec backend composer test:openapi
```

Frontend:

```bash
docker compose exec backend npm test
docker compose exec backend npm run build
docker compose exec frontend npm test -- --watch=false
docker compose exec frontend npm run build
```

Important: do not run multiple backend test processes in parallel against the same `payment_gateway_testing` database.

## Documentation Map

| Topic | Document |
| --- | --- |
| Architecture | [backend/docs/architecture.md](backend/docs/architecture.md) |
| OpenAPI / Swagger | [backend/docs/api/openapi-preparation.md](backend/docs/api/openapi-preparation.md), [backend/docs/api/openapi-generator.md](backend/docs/api/openapi-generator.md) |
| Security | [backend/docs/security.md](backend/docs/security.md) |
| Performance | [backend/docs/performance.md](backend/docs/performance.md) |
| Monitoring | [backend/docs/monitoring.md](backend/docs/monitoring.md) |
| Commands | [backend/docs/commands.md](backend/docs/commands.md) |
| Realtime | [backend/docs/realtime.md](backend/docs/realtime.md) |
| Docker | [backend/docs/docker.md](backend/docs/docker.md) |
| Deployment | [backend/docs/deployment.md](backend/docs/deployment.md) |
| CI/CD | [backend/docs/ci-cd.md](backend/docs/ci-cd.md) |
| Release | [backend/docs/release.md](backend/docs/release.md) |
| Microservices preparation | [backend/docs/microservices.md](backend/docs/microservices.md) |

## Production Notes

- Use `backend/.env.production.example` as baseline
- Keep `APP_DEBUG=false` in production
- Prefer Redis for cache/queue
- Use secure cookie/HSTS settings behind HTTPS
- Do not expose DB/Redis ports publicly in real deployment
- Run migrations intentionally during release process
- See [backend/docs/deployment.md](backend/docs/deployment.md)

## Status / Scope

This repository is a portfolio and architecture-oriented SaaS foundation.

- It demonstrates realistic backend/frontend/platform engineering decisions.
- Billing & Payment Gateway Simulator is implemented as a simulator-safe module, not a real payment integration.
- It does **not** claim turnkey production deployment for every environment.
- Microservices are documented as a **future strategy**; current implementation remains a modular monolith.
