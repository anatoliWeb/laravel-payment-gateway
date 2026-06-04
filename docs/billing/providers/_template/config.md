# Provider Configuration Template

## Platform `.env` Config

Document placeholder environment variable names for platform-owned credentials. Never include real values.

External providers must remain disabled by default until the adapter is implemented and intentionally enabled.

## Customer Database Config

Document fields stored in `payment_provider_accounts`:

- provider key
- display name
- status
- test/live mode
- encrypted credentials
- safe public configuration
- capabilities
- last verification timestamp
- safe metadata

## Encrypted Credentials

- Store secrets through the encrypted provider-account boundary.
- Never place credentials in `public_config` or `metadata`.
- Never return decrypted values from an API resource.

## Public Config

List only non-secret fields safe for internal inspection or future admin display.

## Masked Display

Define which credential fields may be represented in masked form and confirm that full values cannot be reconstructed.

## Test / Live Mode

Document how the provider distinguishes test/sandbox and live modes after verifying official documentation.

## Disabled Provider State

Define when resolution returns `provider_disabled`, `provider_not_configured`, or `provider_credentials_invalid`.
