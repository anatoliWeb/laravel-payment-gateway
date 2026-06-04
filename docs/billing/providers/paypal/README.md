# PayPal Adapter Notes

## Status

Planned only. Not implemented.

## Purpose

Potential future external payment provider integration.

## Required Official Documentation

Implementation must verify current official PayPal documentation before coding.

## Expected Configuration Fields

Use placeholder credential, mode, and safe public-config names only until verified.

## Expected Capabilities

Planned/unknown until official documentation and adapter tests verify support.

## Payment Flow Notes

Keep flow documentation high-level until verified against official provider documentation.

## Webhook / Callback Notes

Do not claim webhook fields or verification algorithms without official verification.

## Error Mapping Notes

Map verified provider failures into stable internal `provider_*` codes.

## Testing Notes

Use fake credentials and an officially documented non-production environment only.

## Security Notes

Encrypt and mask credentials; never commit or log real values.

## Non-Goals

No runtime adapter or real charge execution in Phase 13.5.
