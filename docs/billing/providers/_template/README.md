# Provider Name Adapter Notes

## Status

Planned only until the adapter is implemented and verified against current official provider documentation.

## Purpose

Explain why this provider is useful and which business/payment scenarios it may support.

## Non-Goals

- No unverified API claims.
- No real credentials in documentation.
- No real charge execution during template preparation.

## Required Capabilities

Complete [capabilities.md](./capabilities.md) using verified provider behavior.

## Configuration Fields

Complete [config.md](./config.md). Use placeholder names only until official documentation is verified.

## Credential Storage

Document encrypted customer credentials, platform `.env` configuration, masked admin display, and ownership isolation.

## Payment Flow

Describe the verified high-level charge flow and how responses map to `ProviderPaymentResponseData`.

## Refund Flow

Describe whether refunds are supported and how responses map to `ProviderRefundResponseData`.

## Status Lookup

Describe verified status lookup behavior and internal status mapping.

## Webhook Verification

Complete [webhooks.md](./webhooks.md) using verified signature and replay-protection requirements.

## Error Mapping

Complete [errors.md](./errors.md) and map provider errors to stable internal codes.

## Testing Checklist

Complete [testing.md](./testing.md) before enabling the adapter.

## Security Notes

- Never log provider secrets.
- Never expose decrypted credentials.
- Sanitize metadata and raw responses.
- Require explicit live-mode enablement.
