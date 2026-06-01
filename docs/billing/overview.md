# Billing & Payment Strategy

## Purpose

Billing is a planned module that extends the existing Laravel SaaS foundation.  
It is not a real payment provider integration.  
The target is to implement a Payment Gateway Simulator and demonstrate subscription billing architecture, usage limits, idempotency, webhooks, queue processing, scheduler jobs, invoices, and activity logging.

## Current SaaS Foundation

The current baseline already includes:
- Auth/Sanctum and RBAC
- Chat domain and external webhook endpoints
- Notifications and realtime (Reverb)
- Queue worker + Redis
- Activity logging
- API docs and architecture docs

Billing must integrate into this foundation, not replace it.

## What Is Free

Free plan is for onboarding/demo and meaningful product testing, with strict usage limits.

Typical free scope:
- Basic account access
- Basic dashboard/meta access
- Limited chat usage
- Limited active conversations
- Limited messages per day/month
- Limited or no webhook endpoints
- Short history retention
- No advanced automation
- No dialer access (or very limited demo access)

## What Is Paid

Paid plans unlock larger limits and advanced capabilities.

Chat paid capabilities:
- Higher message and conversation limits
- More webhook endpoints/deliveries
- Longer history retention
- External API access
- Attachment support or higher attachment limits
- Advanced realtime/presence usage
- Priority delivery behavior where relevant

Future dialer paid capabilities:
- Monthly call limits
- Concurrent calls
- SIP accounts
- Recording support/storage
- Dialer webhooks
- Dialer analytics

Platform paid capabilities:
- Higher audit retention
- Higher API/rate limits
- Extended token/scope usage
- Advanced team/role/ops access

## Chat Billing Scope

Chat billing is feature/usage based and should not be hardcoded only for chat internals.

Planned limit keys:
- `chat.messages.daily`
- `chat.messages.monthly`
- `chat.conversations.active`
- `chat.webhook_endpoints.count`
- `chat.webhook_deliveries.monthly`
- `chat.attachments.monthly`
- `chat.history_retention_days`
- `chat.external_api.enabled`
- `chat.realtime.enabled`
- `chat.admin_reply.enabled`
- `chat.search.enabled`

## Future Dialer Billing Reuse

Billing must remain module-agnostic and reusable across domains.

Reusable billing concepts:
- Plans
- Subscriptions
- Plan features
- Feature usage
- Invoices
- Payments
- Payment transactions
- Idempotency keys
- Webhook deliveries
- Scheduler jobs
- Activity logs

Target dialer feature keys:
- `dialer.calls.monthly`
- `dialer.concurrent_calls`
- `dialer.sip_accounts`
- `dialer.recordings.storage_mb`
- `dialer.recordings.retention_days`
- `dialer.webhook_endpoints.count`
- `dialer.analytics.enabled`

## Plan Types

| Plan | Purpose | Target user | Main limits | Notes |
| --- | --- | --- | --- | --- |
| Free | Onboarding/demo | New users | Strict usage limits | Must still allow meaningful testing |
| Basic | Small paid usage | Solo/small teams | Higher chat limits, basic webhooks | Entry paid tier |
| Pro | Production-like usage | Growing teams | Advanced chat/API/webhook limits and retention | Main operational tier |
| Business/Enterprise | High scale and control | Large teams/orgs | High limits, advanced logs, priority operations, dialer-ready features | Customization-ready |

## Usage-Based Limits

Each billable capability should define:
- Limit key
- Period (`daily`, `monthly`, `rolling_30_days`, `lifetime`, `subscription_cycle`)
- Reset policy (scheduler or cycle rollover)
- Hard limit vs soft limit

When exceeded:
- Block action (or degrade behavior by policy)
- Return application-level limit error (commonly `403`, optionally `402`-style semantic)
- Include usage/limit metadata in API response
- Log activity event
- Optionally notify user/admin

## Subscription Lifecycle

Planned statuses:
- `none/free`
- `pending`
- `active`
- `past_due`
- `cancelled`
- `expired`
- `trialing` (optional)
- `suspended` (optional)

Planned transitions:
- User selects plan -> subscription created (`pending` for paid, `active` for free as policy)
- Paid selection -> payment created
- Payment success -> subscription `active`/renewed
- Payment failure -> remains `pending` or transitions to `past_due`
- Cancellation -> immediate or period-end access by plan policy
- Expiration/past-due enforcement handled by scheduler

## Payment Lifecycle

Planned statuses:
- `created` / `pending`
- `processing`
- `succeeded`
- `failed`
- `expired`
- `cancelled`
- `refunded` (future optional)

Simulator responsibilities:
- Create payment records
- Transition statuses based on simulated outcomes
- Trigger transaction history entries
- Queue webhook events
- Activate/renew subscription only on successful payment

## Invoice Lifecycle

Planned statuses:
- `draft`
- `issued`
- `payment_pending`
- `paid`
- `failed`
- `void`
- `overdue`

Notes:
- Invoice may represent subscription cycle or one-time upgrade
- Invoice references payment flow
- MVP invoice shape can stay simple while preserving domain clarity

## Webhook Lifecycle

Planned flow:
- Event created
- Payload built
- Delivery queued
- Delivery attempted
- Delivered or failed
- Retry scheduled when needed
- `permanently_failed` after retry policy exhausted

Planned events:
- `payment.succeeded`
- `payment.failed`
- `subscription.activated`
- `subscription.cancelled`
- `invoice.paid`
- `usage.limit_exceeded`

Delivery requirements:
- Async queue delivery
- Retry + backoff strategy
- Safe logging (no secrets in logs)
- Future HMAC/signature support

## Scheduler / Cron Responsibilities

Planned idempotent commands:
- Expire pending payments
- Reset usage counters (daily/monthly/cycle)
- Expire subscriptions
- Detect/apply `past_due` transitions
- Retry failed webhook deliveries
- Clean old webhook delivery records
- Clean old activity logs by retention policy
- Generate recurring invoices (if enabled)
- Send billing reminders (if enabled)

## Activity Logging Strategy

Log high-value billing events:
- Subscription created/activated/cancelled/expired
- Payment created/succeeded/failed/expired
- Invoice issued/paid/failed/void/overdue
- Webhook delivered/failed/retried
- Usage limit exceeded
- Idempotency replay/conflict

Use structured, non-secret logs aligned with current platform logging approach.

## API / UX Behavior

Planned API behavior:
- Consistent response envelope
- Clear status and reason codes for billing transitions
- Usage metadata in limit responses
- Predictable idempotency behavior for payment creation
- Async webhook delivery visibility via history/status endpoints

Planned UX behavior:
- User can see current plan, limits, and usage
- User receives clear reason when action is blocked by limits
- Upgrade path is explicit from free-limit boundaries

## Non-Goals For This Stage

- No real payment provider integration (Stripe/PayPal/etc.)
- No billing models/migrations/services/controllers implementation yet
- No route-level billing feature delivery yet
- No production payment processing claims

## Implementation Notes For Next Phases

Next implementation phases should start with:
1. Plans and feature-access schema design
2. Feature usage tracking model
3. Subscription and payment lifecycle entities
4. Idempotency and webhook delivery architecture
5. Scheduler command contracts and test strategy

Detailed Phase 2 planning: [Billing Plans & Feature Access Design](./plans.md).
Detailed Phase 3 planning: [Payment Gateway Simulator Design](./payment-gateway-simulator.md).
Detailed Phase 4 planning: [Billing Database Schema Planning](./database.md).

## Status

- Phase 1 is strategy/documentation only.
- No billing/payment code has been implemented yet.
- Next phase: Plans & Feature Access Design.
