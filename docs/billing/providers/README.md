# Payment Provider Adapter Documentation

## Purpose

This folder is the repeatable documentation convention for payment provider adapters.

Runtime currently supports simulator-safe provider behavior only. Every real provider listed here is planned, not implemented.

## Folder Convention

```text
providers/
|-- README.md
|-- _template/
|   |-- README.md
|   |-- capabilities.md
|   |-- config.md
|   |-- webhooks.md
|   |-- errors.md
|   `-- testing.md
|-- simulator/
`-- <planned-provider>/
```

Each provider folder must explain capabilities, configuration, credential handling, webhook verification, error mapping, and testing.

## Supported Runtime Providers

- [Simulator](./simulator/README.md): implemented demo-safe adapter with no external HTTP calls.

## Planned Providers

- [Stripe](./stripe/README.md)
- [PayPal](./paypal/README.md)
- [LiqPay](./liqpay/README.md)
- [WayForPay](./wayforpay/README.md)
- [PrivatBank / Privat24](./privat24/README.md)
- [UKRSIBBANK](./ukrsibbank/README.md)
- [Oschadbank](./oschadbank/README.md)

Planned provider availability, capabilities, configuration fields, and API details must be verified against official provider documentation before implementation.

## How to Add a New Provider

1. Copy the `_template` documentation folder.
2. Verify current official provider documentation.
3. Define the provider key and required capabilities.
4. Implement `PaymentProviderInterface`.
5. Map provider requests and responses through existing provider DTOs.
6. Add platform `.env` and customer database configuration rules.
7. Define encrypted credential and masked-display rules.
8. Document webhook signature verification and replay protection.
9. Map errors to stable internal provider codes.
10. Add adapter, config resolver, and webhook verification tests.

## Required Files

Use the templates:

- [Provider README](./_template/README.md)
- [Capabilities](./_template/capabilities.md)
- [Configuration](./_template/config.md)
- [Webhook Verification](./_template/webhooks.md)
- [Error Mapping](./_template/errors.md)
- [Testing Checklist](./_template/testing.md)

## Required Runtime Classes

A real provider adapter must:

- implement `PaymentProviderInterface`
- consume and return provider DTOs
- resolve configuration through `PaymentProviderConfigResolver`
- avoid Laravel Request and Eloquent dependencies in the provider contract
- sanitize provider metadata and raw responses

## Required Tests

At minimum:

- provider factory resolution
- charge/refund/status adapter behavior
- config resolver source priority
- credentials encryption/masking
- ownership isolation
- webhook signature verification
- stable error mapping
- confirmation that tests do not execute real charges

## Credential Safety

- Never commit real credentials.
- Store customer credentials encrypted.
- Return masked credentials only.
- Never log decrypted credentials.
- Never place raw card data or CVV/CVC data in provider configuration.

## Demo vs Sandbox vs Live Mode

- `demo`: internal simulator behavior with no external calls.
- `sandbox` or provider test mode: future real adapter connected only to an officially documented non-production environment.
- `live`: future production provider mode requiring explicit enablement, verified credentials, operational controls, and security review.

The current project runs in demo/simulator mode.

## Status

Phase 13.5 provides documentation templates and planned-provider notes. It does not add real provider adapters.
