# Billing User Portal UI

## Purpose

This page documents the user-facing billing portal implemented in the Angular dashboard.

The portal is intentionally honest about the current backend surface:

- live sections use real billing API endpoints
- missing domain views are shown as explicit placeholders
- simulator-safe actions are available for supported user settings

## Route

- Frontend route: `/billing`
- Frontend module: `frontend/src/app/features/billing/`

## Live Sections

These sections consume real backend endpoints:

- Wallet balances from `GET /api/v1/billing/wallet`
- Wallet transactions from `GET /api/v1/billing/wallet/transactions`
- Invoice history from `GET /api/v1/billing/invoices`
- Payment methods from `GET /api/v1/billing/payment-methods`
- Payment method creation from `POST /api/v1/billing/payment-methods`
- Payment method default selection from `POST /api/v1/billing/payment-methods/{paymentMethod}/set-default`
- Payment method deactivation from `DELETE /api/v1/billing/payment-methods/{paymentMethod}`
- Payment preferences from `GET /api/v1/billing/payment-preferences`
- Payment preference updates from `PATCH /api/v1/billing/payment-preferences`

## Placeholder Sections

These sections are rendered as design references because the backend does not expose dedicated user-centric endpoints yet:

- Current subscription
- Current plan and limits
- Available plans
- Usage limits
- Payment history

## Design Choices

- The portal lives in the Angular dashboard because it is the user-facing application.
- The screen uses the shared component library already present in the repo.
- Loading, empty, and error states are shown per section so failures are visible without hiding the rest of the page.
- Actions are limited to simulator-safe updates only.
- Missing API areas are not hidden behind fake data, so the portal does not pretend those endpoints exist.

## Notes

- No business logic is duplicated on the frontend.
- No real payment provider integration is added here.
- The page is ready for later expansion once user-scoped subscription, plans, usage, and payment-history APIs are exposed.
