# Documentation

## Overview

This directory contains technical documentation for the project.

Project identity: Laravel SaaS Billing Platform with Payment Gateway Simulator.

It covers system architecture, development workflow, and operational guidelines for both backend and frontend parts of the application.

The project follows an API-first, modular architecture with clear separation between services.

---

## Structure

- [architecture.md](./architecture.md) - System design, components, and data flow
- [commands.md](./commands.md) - Development and operational commands
- [coding-standards.md](./coding-standards.md) - Code style and best practices
- [api.md](./api.md) - API endpoints and examples
- [billing/overview.md](./billing/overview.md) - Billing strategy and roadmap scope
- [billing/plans.md](./billing/plans.md) - Plans and feature-access design
- [billing/payment-gateway-simulator.md](./billing/payment-gateway-simulator.md) - Payment Gateway Simulator design
- [billing/database.md](./billing/database.md) - Billing database schema planning
- [billing/architecture.md](./billing/architecture.md) - Billing domain structure architecture
- [billing/api.md](./billing/api.md) - Billing API contract planning
- [billing/statuses.md](./billing/statuses.md) - Billing enums, statuses, and transition rules
- [billing/seeders.md](./billing/seeders.md) - Billing seed data and idempotent seeding strategy
- [billing/overrides.md](./billing/overrides.md) - Billing restrictions and manual feature overrides
- [billing/rbac.md](./billing/rbac.md) - Billing RBAC permissions and admin access model
- [billing/future-dialer.md](./billing/future-dialer.md) - Future dialer billing extension
- [billing/currencies.md](./billing/currencies.md) - Currency and exchange-rate foundation
- [billing/wallets.md](./billing/wallets.md) - User wallet balance foundation
- [billing/payment-methods.md](./billing/payment-methods.md) - Payment methods and user payment preferences foundation
- [billing/payment-risk.md](./billing/payment-risk.md) - Demo-safe payment risk and fraud guard
- [billing/auto-top-up.md](./billing/auto-top-up.md) - Auto top-up and auto charge foundation
- [billing/payment-api.md](./billing/payment-api.md) - Wallet/card payment API interface
- [billing/payment-simulation.md](./billing/payment-simulation.md) - Simulator-safe payment status transition flow
- [billing/payment-events.md](./billing/payment-events.md) - Billing domain events and post-event action hooks
- [billing/scheduler.md](./billing/scheduler.md) - Cron and scheduler commands for billing runtime maintenance
- [billing/subscription-lifecycle.md](./billing/subscription-lifecycle.md) - Subscription activation, plan changes, cancellation, renewal, and past-due handling
- [billing/invoices.md](./billing/invoices.md) - Invoice lifecycle, ownership, and payment linking
- [billing/wallet-adjustments.md](./billing/wallet-adjustments.md) - Permission-gated manual wallet credit/debit API
- [billing/idempotency.md](./billing/idempotency.md) - Central billing write replay and conflict protection
- [billing/ownership-scope.md](./billing/ownership-scope.md) - Company/seller ownership foundation and demo seed data
- [billing/payment-providers.md](./billing/payment-providers.md) - External provider integration readiness
- [billing/webhooks.md](./billing/webhooks.md) - Outbound billing webhook delivery flow
- [billing/providers/README.md](./billing/providers/README.md) - Provider adapter documentation convention
- [billing/providers/_template/README.md](./billing/providers/_template/README.md) - Provider adapter documentation template
- [billing/providers/simulator/README.md](./billing/providers/simulator/README.md) - Implemented simulator provider notes
- [billing/providers/privat24/README.md](./billing/providers/privat24/README.md) - Planned PrivatBank / Privat24 provider notes
- [billing/providers/ukrsibbank/README.md](./billing/providers/ukrsibbank/README.md) - Planned UKRSIBBANK provider notes
- [billing/providers/oschadbank/README.md](./billing/providers/oschadbank/README.md) - Planned Oschadbank provider notes
- [TODO.md](../TODO.md) - Development roadmap and task tracking

---

## Quick Navigation

- Architecture -> [./architecture.md](./architecture.md)
- API -> [./api.md](./api.md)
- Billing -> [./billing/overview.md](./billing/overview.md)
- Billing Plans -> [./billing/plans.md](./billing/plans.md)
- Billing Simulator -> [./billing/payment-gateway-simulator.md](./billing/payment-gateway-simulator.md)
- Billing Database -> [./billing/database.md](./billing/database.md)
- Billing Architecture -> [./billing/architecture.md](./billing/architecture.md)
- Billing API -> [./billing/api.md](./billing/api.md)
- Billing Statuses -> [./billing/statuses.md](./billing/statuses.md)
- Billing Seeders -> [./billing/seeders.md](./billing/seeders.md)
- Billing Overrides -> [./billing/overrides.md](./billing/overrides.md)
- Billing RBAC -> [./billing/rbac.md](./billing/rbac.md)
- Future Dialer Billing -> [./billing/future-dialer.md](./billing/future-dialer.md)
- Billing Currencies -> [./billing/currencies.md](./billing/currencies.md)
- Billing Wallets -> [./billing/wallets.md](./billing/wallets.md)
- Billing Payment Methods -> [./billing/payment-methods.md](./billing/payment-methods.md)
- Billing Payment Risk -> [./billing/payment-risk.md](./billing/payment-risk.md)
- Billing Auto Top-Up -> [./billing/auto-top-up.md](./billing/auto-top-up.md)
- Billing Payment API -> [./billing/payment-api.md](./billing/payment-api.md)
- Billing Payment Simulation -> [./billing/payment-simulation.md](./billing/payment-simulation.md)
- Billing Payment Events -> [./billing/payment-events.md](./billing/payment-events.md)
- Billing Scheduler -> [./billing/scheduler.md](./billing/scheduler.md)
- Billing Subscription Lifecycle -> [./billing/subscription-lifecycle.md](./billing/subscription-lifecycle.md)
- Billing Invoices -> [./billing/invoices.md](./billing/invoices.md)
- Billing Webhooks -> [./billing/webhooks.md](./billing/webhooks.md)
- Wallet Adjustments -> [./billing/wallet-adjustments.md](./billing/wallet-adjustments.md)
- Billing Idempotency -> [./billing/idempotency.md](./billing/idempotency.md)
- Billing Ownership Scope -> [./billing/ownership-scope.md](./billing/ownership-scope.md)
- Billing Payment Providers -> [./billing/payment-providers.md](./billing/payment-providers.md)
- Provider Adapter Docs -> [./billing/providers/README.md](./billing/providers/README.md)
- Provider Template -> [./billing/providers/_template/README.md](./billing/providers/_template/README.md)
- Simulator Provider -> [./billing/providers/simulator/README.md](./billing/providers/simulator/README.md)
- Ukrainian Provider Notes -> [./billing/providers/privat24/README.md](./billing/providers/privat24/README.md)
- Commands -> [./commands.md](./commands.md)
- Coding Standards -> [./coding-standards.md](./coding-standards.md)
- TODO -> [../TODO.md](../TODO.md)

---

## Purpose

This documentation is designed to:

- Help developers quickly understand the system
- Provide consistent development guidelines
- Explain architectural decisions
- Simplify onboarding and collaboration

---

## Development Workflow

The project follows a structured, incremental workflow:

1. Review tasks in `TODO.md`
2. Implement features step-by-step
3. Use small, meaningful commits
4. Validate changes in Docker environment
5. Keep frontend and backend in sync

---

## Best Practices

- Keep code modular and maintainable
- Use environment variables for configuration
- Avoid hardcoded values
- Follow consistent naming conventions
- Document non-obvious decisions (WHY comments)

---

## System Notes

- All services run in Docker
- Backend (Laravel) handles API and admin panel
- Frontend (React) is a separate SPA
- RBAC system controls access (roles + permissions + overrides)
- Token-based authentication via Sanctum

---

## Screenshots

Below are key UI screens demonstrating system functionality.

---

### Frontend (React SPA)

#### Dashboard
Shows system statistics and recent activity.
![Dashboard](./screenshots/frontend/dashboard-overview.png)

#### Users Management
User list with roles and permissions overview.
![Users](./screenshots/frontend/users-list.png)

#### RBAC Permissions (User Edit)
Role-based and manual permissions with override support.
![RBAC](./screenshots/frontend/users-rbac-edit.png)

#### Tokens Management
API token list for secure access control.
![Tokens](./screenshots/frontend/tokens-list.png)

#### Token Creation
Token is shown once after creation (secure UX).
![Create Token](./screenshots/frontend/tokens-create-modal.png)

---

### Backend (Blade Admin Panel)

#### Dashboard
Admin metrics and system overview.
![Admin Dashboard](./screenshots/backend/admin-dashboard.png)

#### Users Management
Full user management interface.
![Admin Users](./screenshots/backend/admin-users-list.png)

#### RBAC (Admin)
Roles and permissions assignment in admin panel.
![Admin RBAC](./screenshots/backend/admin-users-rbac.png)

#### Roles Management
Manage system roles with restrictions.
![Roles](./screenshots/backend/admin-roles.png)

#### Permissions Management
Centralized permissions control.
![Permissions](./screenshots/backend/admin-permissions.png)

#### Tokens Management
Admin-level token management.
![Admin Tokens](./screenshots/backend/admin-tokens.png)

---

## Future Documentation

Planned improvements:

- API specification (OpenAPI / Postman)
- Deployment guide
- Monitoring and logging setup
- Scaling strategy

---

## Summary

This documentation reflects a structured approach to building a scalable SaaS-like system with clear separation of concerns and production-ready practices.

---

<!-- WHY:
Improves developer navigation and onboarding experience.
-->
## Related Documentation

- [Architecture](./architecture.md)
- [API](./api.md)
- [Billing Strategy](./billing/overview.md)
- [Billing Plans Design](./billing/plans.md)
- [Payment Gateway Simulator Design](./billing/payment-gateway-simulator.md)
- [Billing Database Schema Planning](./billing/database.md)
- [Billing Domain Architecture](./billing/architecture.md)
- [Billing API Contract](./billing/api.md)
- [Billing Enums & Statuses](./billing/statuses.md)
- [Billing Seeders](./billing/seeders.md)
- [Billing Overrides](./billing/overrides.md)
- [Billing RBAC](./billing/rbac.md)
- [Future Dialer Billing](./billing/future-dialer.md)
- [Billing Currencies](./billing/currencies.md)
- [Billing Wallets](./billing/wallets.md)
- [Billing Payment Methods](./billing/payment-methods.md)
- [Billing Payment Risk](./billing/payment-risk.md)
- [Billing Auto Top-Up](./billing/auto-top-up.md)
- [Billing Payment API](./billing/payment-api.md)
- [Billing Payment Simulation](./billing/payment-simulation.md)
- [Billing Webhooks](./billing/webhooks.md)
- [Billing Scheduler](./billing/scheduler.md)
- [Billing Subscription Lifecycle](./billing/subscription-lifecycle.md)
- [Billing Ownership Scope](./billing/ownership-scope.md)
- [Billing Payment Providers](./billing/payment-providers.md)
- [Commands](./commands.md)
- [Coding Standards](./coding-standards.md)
- [Main Docs](./README.md)
