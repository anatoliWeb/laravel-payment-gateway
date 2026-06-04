# Provider Error Mapping Template

## Purpose

Map provider-specific failures into stable internal codes without leaking provider internals.

| Internal Code | Intended Meaning |
| --- | --- |
| `provider_not_configured` | No usable provider configuration exists. |
| `provider_disabled` | Provider use is explicitly disabled. |
| `provider_credentials_invalid` | Credentials are missing, invalid, or rejected. |
| `provider_charge_failed` | Charge operation failed without a more stable internal reason. |
| `provider_timeout` | Provider request exceeded the configured timeout. |
| `provider_unsupported_operation` | Requested capability is not supported. |
| `provider_webhook_signature_invalid` | Inbound webhook verification failed. |

## Mapping Rules

- Preserve safe retryability information where useful.
- Do not expose raw provider responses or credentials.
- Sanitize provider messages before logging.
- Keep internal codes stable when provider wording changes.
