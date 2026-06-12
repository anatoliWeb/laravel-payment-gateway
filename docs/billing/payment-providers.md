# External Payment Provider Integration Readiness

## Purpose

Phase 13.4 prepares a provider-neutral payment boundary without connecting real payment systems.

The default runtime adapter remains the internal simulator. Future Stripe, PayPal, LiqPay, WayForPay, Monobank, or Fondy adapters can implement the same contract without rewriting core payment creation.

## Non-Goals

This phase does not:
- execute real external charges or refunds
- include real provider SDKs
- expose inbound provider webhook routes
- implement webhook delivery
- implement full idempotency replay/conflict storage
- activate or renew subscriptions
- complete multi-tenant ownership

## Provider Abstraction

Provider code lives under:

```text
app/Services/Payments/Providers/
|-- Contracts/
|-- DTO/
|-- Simulator/
|-- PaymentProviderFactory.php
`-- PaymentProviderConfigResolver.php
```

Core payment logic depends on the provider contract and DTOs, not provider SDKs or HTTP requests.

## PaymentProviderInterface

The provider contract supports:
- `charge`
- `refund`
- `getStatus`
- `verifyWebhook`
- `capabilities`

The interface does not depend on Laravel Request or Eloquent models.

## Provider DTOs

Provider request/response boundaries include:
- charge and refund data
- payment/refund responses
- status response
- webhook input/result
- capabilities
- stable error data
- resolved provider config

DTOs are immutable-like data carriers. Provider-specific errors map to stable codes such as:
- `provider_not_configured`
- `provider_disabled`
- `provider_credentials_invalid`
- `provider_charge_failed`
- `provider_timeout`
- `provider_unsupported_operation`
- `provider_webhook_signature_invalid`

## Simulator Provider

`SimulatorPaymentProvider` performs no HTTP calls.

Behavior:
- `fake_card`: returns `simulator`, `processing`, and `sim_*` reference
- `fake_manual_invoice`: returns `manual`, `pending`, and `manual_*` reference
- `fake_wallet`: returns `internal_wallet`, `pending`, and `wallet_*` reference
- refund: returns safe fake `refunded` result
- status lookup: returns deterministic fake status
- webhook verification: accepts only the documented simulator test signature

## Platform `.env` Provider Config

Platform-owned configuration is defined through `config/billing.php`.

Safe defaults:

```env
PAYMENT_PROVIDER=simulator
PAYMENT_PLATFORM_PROVIDER=simulator
PAYMENT_EXTERNAL_PROVIDERS_ENABLED=false
PAYMENT_SIMULATOR_DEFAULT_ENABLED=true
```

Optional external provider variables are placeholders only. They are not required for local setup and external providers remain disabled by default.

Example shape for future provider flags:

```env
PAYMENT_PROVIDER=simulator
PAYMENT_EXTERNAL_PROVIDERS_ENABLED=false
PAYMENT_PROVIDER_TIMEOUT_SECONDS=15
PAYMENT_PROVIDER_RETRY_ATTEMPTS=3
```

## Customer Database Provider Config

`payment_provider_accounts` stores provider accounts with a required custodial `user_id` and optional additive company/seller scope.

Current ownership boundary:
- every account still belongs to one required `user_id`
- an account may additionally target one company or seller scope
- explicit account resolution must match the requested seller, company, or unscoped user context
- cross-scope explicit account resolution fails with `provider_account_not_accessible`

This is not presented as complete tenant isolation because company/seller accounts retain a custodial user. The ownership foundation and boundaries are documented in [Company / Seller Ownership Scope](./ownership-scope.md).

Example shape for a stored provider account:

```json
{
  "provider": "simulator",
  "mode": "demo",
  "display_name": "Demo provider account",
  "is_active": true,
  "credentials": {
    "client_id": "masked-value",
    "client_secret": "masked-value",
    "merchant_id": "masked-value"
  }
}
```

## Credential Encryption and Masking

`PaymentProviderAccount`:
- encrypts credential JSON using Laravel Crypt
- hides encrypted payload from model serialization
- decrypts only inside the provider config boundary
- provides masked credential output for future admin forms
- rejects raw card/CVV-like fields

No real credentials exist in seeders or tests.

Admin forms should expose only masked and safe metadata:

- provider key
- display name
- mode
- status
- active scope
- last verification timestamp
- masked credential summary

They must never expose decrypted credentials, full webhook secrets, or raw card data.

## Provider Config Priority

Resolution priority:
1. explicit provider account when supplied and matching the active scope
2. active seller-scoped database provider account
3. active company-scoped database provider account
4. active unscoped user-owned database provider account
5. enabled platform config from `.env`
6. simulator/manual/internal-wallet default in demo mode
7. disabled/not configured result

## Provider Capabilities

Capabilities explicitly describe support for:
- charges
- refunds
- status lookup
- webhook verification
- manual invoice
- redirect flow
- tokenized card

The simulator supports charges, refunds, status lookup, webhook verification, and manual invoice behavior. It does not claim redirect or tokenized-card support.

## Provider Error Mapping

Adapters return stable errors through provider response DTOs. Core services surface these stable codes without exposing raw provider payloads.

Timeout/retry policy is configuration-ready through:
- `PAYMENT_PROVIDER_TIMEOUT_SECONDS`
- `PAYMENT_PROVIDER_RETRY_ATTEMPTS`

No external retry execution exists because no real adapter is connected.

## Provider Webhook Verification

The contract includes webhook verification, and the simulator has predictable fake signature validation for direct tests.

No public provider webhook endpoint is created in Phase 13.4.

Phase 16 implements outbound billing webhooks only: our system sends signed delivery callbacks to client URLs after simulator payment status changes. Real inbound provider webhooks remain future provider-specific work.

Example verification responsibilities for a real adapter:

- validate signature header or query token
- validate timestamp or replay window
- compare HMAC over the raw payload
- map invalid signatures to `provider_webhook_signature_invalid`
- reject unknown event types with a stable internal error

## Planned Provider Adapters

Planned adapters:
- Stripe
- PayPal
- LiqPay
- WayForPay
- Monobank/Fondy

They are documented plans only. Empty adapter classes are intentionally not created.

## Adapter Template Documentation

Use the provider template docs in `docs/billing/providers/_template` to keep future adapters consistent:

- README
- capabilities
- configuration
- webhook verification
- error mapping
- testing checklist

The templates are documentation scaffolding only and do not imply an implemented provider.

## How to Add a New Provider

Future provider work should:
1. implement `PaymentProviderInterface`
2. declare capabilities
3. map provider responses/errors into stable DTOs
4. resolve credentials only through `PaymentProviderConfigResolver`
5. verify webhook signatures through the contract
6. sanitize metadata and raw responses
7. add provider-specific tests and documentation

The reusable provider template/documentation folder is intentionally reserved for Phase 13.5.

## Provider Documentation Folders

Repeatable provider documentation lives under [Payment Provider Adapter Documentation](./providers/README.md).

Templates:
- [Provider README template](./providers/_template/README.md)
- [Capabilities template](./providers/_template/capabilities.md)
- [Configuration template](./providers/_template/config.md)
- [Webhook verification template](./providers/_template/webhooks.md)
- [Error mapping template](./providers/_template/errors.md)
- [Testing checklist template](./providers/_template/testing.md)

Implemented demo provider:
- [Simulator](./providers/simulator/README.md)

Planned provider notes:
- [Stripe](./providers/stripe/README.md)
- [PayPal](./providers/paypal/README.md)
- [LiqPay](./providers/liqpay/README.md)
- [WayForPay](./providers/wayforpay/README.md)
- [PrivatBank / Privat24](./providers/privat24/README.md)
- [UKRSIBBANK](./providers/ukrsibbank/README.md)
- [Oschadbank](./providers/oschadbank/README.md)

Real provider availability, capabilities, API details, configuration fields, and webhook verification rules must be verified against current official provider documentation before implementation.

## Payment Source and Provider Permissions

Sensitive source/provider use can require explicit permission in future API/provider hardening phases.

Permission-gated wallet adjustment is the first implemented example of actor authorization for a financial operation. Future naming may include:
- `billing.payment_sources.use.wallet`
- `billing.payment_sources.use.payment_method`
- `billing.payment_sources.use.manual_wallet_adjustment`
- `billing.payment_sources.use.manual_invoice`
- `billing.payment_sources.use.simulator`
- `billing.payment_sources.use.external_provider`
- `billing.providers.use.simulator`
- `billing.providers.use.stripe`
- `billing.providers.use.paypal`
- `billing.providers.use.liqpay`
- `billing.providers.use.wayforpay`
- `billing.providers.use.privat24`
- `billing.providers.use.ukrsibbank`
- `billing.providers.use.oschadbank`

Current simulator/internal/manual source-provider readiness permissions are seeded for admin but are not enforced on normal payment flows. Future real-provider permissions are documentation-only, and no real provider integration is enabled. Permission checks would complement ownership, risk guards, provider config validation, capability checks, and idempotency.

The simulator payment path is protected by central `payment.create` idempotency before provider execution. Future external adapters should additionally use a `provider.charge` scope and derive or safely forward a provider idempotency key. Raw unsafe metadata and credentials must never be forwarded.

## Admin Form Readiness

Future admin settings may display:
- provider
- display name
- status
- test/live mode
- public configuration
- capabilities
- last verification timestamp
- masked credentials

Admin APIs must never return decrypted credentials.

## Testing Strategy

Tests cover:
- simulator provider behavior
- provider factory and external-provider disabling
- default/env/database/disabled config resolution
- encrypted credentials and masking
- cross-user account isolation
- seller/company/user account priority and cross-scope isolation
- stable fake webhook verification
- existing payment creation regression

## Status

Phase 13.4 implements external payment provider integration readiness.

Real provider integration remains intentionally disabled in portfolio/demo mode.

Phase 14.1 adds company/seller provider-account scope without claiming a complete marketplace credential model.
