# WayForPay Adapter Notes

## Status

Planned only. Not implemented.

## Purpose

Potential future payment provider integration for relevant markets.

## Required Official Documentation

Implementation must verify current official WayForPay documentation before coding.

## Expected Configuration Fields

Use placeholder merchant, secret, mode, and safe public-config names only until verified.

## Expected Capabilities

Planned/unknown until official documentation and adapter tests verify support.

## Payment Flow Notes

Document only verified high-level payment behavior.

## Webhook / Callback Notes

Do not claim callback fields or verification algorithms without official verification.

## Error Mapping Notes

Map verified failures into stable internal `provider_*` codes.

## Testing Notes

Use fake credentials and an officially documented non-production environment only.

## Security Notes

Customer credentials must be encrypted, isolated, and masked.

## Non-Goals

No runtime adapter or real charge execution in Phase 13.5.
