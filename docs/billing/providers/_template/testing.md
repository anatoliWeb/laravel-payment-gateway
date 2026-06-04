# Provider Testing Checklist

## Factory Tests

- provider key resolves the intended adapter
- unknown provider returns a controlled error
- disabled provider cannot be used

## Adapter Tests

- charge mapping
- refund mapping
- status lookup mapping
- capabilities
- metadata/raw-response sanitization

## Config Resolver Tests

- explicit owned provider account
- customer database account priority
- platform `.env` fallback
- disabled/not-configured behavior
- cross-customer access denial

## Webhook Verification Tests

- valid signature
- invalid signature
- replay/duplicate event behavior
- unknown event handling
- sanitized payload storage

## Credential Tests

- credentials encrypted at rest
- masked output hides full values
- decrypted credentials never serialized
- raw payment data rejected

## Safety Requirements

- use fake credentials only
- use demo/sandbox mode only
- do not execute real external charges
- do not require real provider credentials for the default test suite
