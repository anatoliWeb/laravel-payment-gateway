# Laravel SaaS Billing Platform with Payment Gateway Simulator

API-first SaaS foundation у форматі modular monolith: Laravel backend, Vue Admin (всередині backend), Angular Dashboard, RBAC, chat/realtime, OpenAPI-документація, Docker та CI/CD/release preparation.

Billing-напрямок цього репозиторію: planned Billing & Payment Gateway Simulator module поверх існуючого SaaS baseline.

## Ключові можливості

- API-first Laravel backend (`/api/v1`)
- Розділена frontend-архітектура: Vue Admin + Angular Dashboard
- RBAC із permission-aware навігацією та контролем доступу до API
- Chat-модуль: conversations, messages, participants, typing, presence, attachments, webhooks, external API
- OpenAPI/Swagger з permission-aware docs portal і filtered spec
- Foundations для Redis cache/queue та queue worker strategy
- Foundations для realtime на Laravel Reverb
- Dockerized локальне середовище (backend/mysql/redis/nginx/queue/reverb/frontend)
- Security hardening foundations (rate limiting, secure headers, token/validation hardening, realtime auth)
- Monitoring/logging foundations (health endpoints, structured logs, queue/realtime/container logging)
- Modular monolith архітектура з документованою future microservices strategy

## Технологічний стек

### Backend

- PHP 8.3
- Laravel 13
- MySQL 8
- Redis 7
- Laravel Sanctum
- Laravel Reverb
- dedoc/scramble (OpenAPI)

### Frontend

- Vue 3 + Pinia + Vue Router (Admin, через Vite)
- Angular 21 (Dashboard)
- SCSS

### Infrastructure / DevOps

- Docker Compose
- Nginx
- Queue worker + Horizon profile
- GitHub Actions CI

## Огляд архітектури

Поточна архітектура - **modular monolith** з API-first boundaries, service-layer organization, event-driven side effects і задокументованою extraction strategy для майбутніх microservices.

- Деталі архітектури: [backend/docs/architecture.md](backend/docs/architecture.md)
- План майбутнього extraction: [backend/docs/microservices.md](backend/docs/microservices.md)

## Основні функції

### Billing Roadmap

- Planned Billing & Payment Gateway Simulator module
- Planned subscription та paid feature flows
- Planned idempotency і webhook simulation для payment workflow
- Planned billing reuse для future dialer/calling monetization сценаріїв

### Auth & RBAC

- Session-first auth із bearer/token support
- Roles/permissions з middleware enforcement
- Permission-aware docs і admin-навігація

### Chat & Realtime

- Direct/group conversations та messaging
- Participant roles/access states/capabilities
- Політики upload/download для attachments
- Read/delivery/device-read states
- Typing і presence channels
- Інтеграція з webhooks та external API
- Foundations для safe realtime payload

### API документація

- Permission-aware docs portal: `/docs/api/portal`
- User-filtered spec: `/docs/api.filtered.json`
- Raw Swagger UI/spec для full-access users: `/docs/api`, `/docs/api.json`
- OpenAPI generation через Scramble

### Security

- Rate limiting policy
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
- CI/CD preparation та release workflow preparation

## Локальний запуск

Використовуйте корінь репозиторію як робочу директорію.

```bash
cp backend/.env.example backend/.env
docker compose up -d
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --seed
docker compose exec backend npm ci
docker compose exec backend npm run build
```

Angular dashboard (за потреби):

```bash
docker compose exec frontend npm ci
docker compose exec frontend npm run build
```

## Корисні URL

На базі стандартних `docker-compose.yml` / `.env`:

- Backend (Nginx): `http://localhost:8080`
- API base: `http://localhost:8080/api/v1`
- Vue Admin (Vite dev): `http://localhost:5173`
- Angular Dashboard: `http://localhost:4200`
- API docs portal: `http://localhost:8080/docs/api/portal`
- Swagger UI (full-access policy): `http://localhost:8080/docs/api`
- Public liveness: `http://localhost:8080/health`

## Тестування

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

Важливо: не запускайте кілька backend test processes паралельно проти однієї `payment_gateway_testing` бази.

## Карта документації

| Тема | Документ |
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

## Production-примітки

- Використовуйте `backend/.env.production.example` як baseline
- Тримайте `APP_DEBUG=false` у production
- Використовуйте Redis для cache/queue
- Використовуйте secure cookie/HSTS settings за HTTPS
- Не відкривайте DB/Redis порти публічно в реальному deployment
- Запускайте міграції усвідомлено в межах release process
- Деталі: [backend/docs/deployment.md](backend/docs/deployment.md)

## Статус і межі

Цей репозиторій - портфоліо та architecture-oriented SaaS foundation.

- Він демонструє реалістичні backend/frontend/platform engineering рішення.
- Billing & Payment Gateway Simulator є planned module і не заявляється як повністю реалізований на поточному етапі.
- Він **не** заявляє turnkey production deployment для будь-якого оточення без додаткового налаштування.
- Microservices описані як **future strategy**; поточна реалізація залишається modular monolith.


