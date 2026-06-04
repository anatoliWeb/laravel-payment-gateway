# PrivatBank / Privat24 Adapter Notes

## Status

Planned bank/provider integration notes only. Not implemented.

## Purpose

Document a possible future integration relevant to Ukrainian customers without claiming current API availability or capabilities.

## Required Official Documentation

Any implementation must first verify current official PrivatBank / Privat24 documentation, commercial availability, supported payment products, and integration requirements.

## Expected Configuration Fields

Use placeholder credential, merchant/account, mode, and safe public-config names only after official verification.

## Expected Capabilities

Unknown until official documentation is verified.

## Payment Flow Notes

High-level planning only. No unverified merchant API or endpoint claims are made.

## Webhook / Callback Notes

Signature, callback, and event behavior must be verified from official documentation before implementation.

## Error Mapping Notes

Map verified failures into stable internal `provider_*` codes.

## Testing Notes

Use fake credentials and an officially documented non-production environment only, if available.

## Security Notes

No real credentials in the repository. Credentials must be encrypted, isolated, and masked.

## Non-Goals

No runtime adapter, real bank integration, or real charge execution in Phase 13.5.
