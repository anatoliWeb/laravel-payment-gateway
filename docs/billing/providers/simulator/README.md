# Simulator Provider

## Status

Implemented demo-safe runtime provider.

## Purpose

The simulator exercises provider-oriented architecture without connecting a real payment system.

## External Calls

The simulator performs no external HTTP calls and executes no real charges or refunds.

## Fake Card Charge

`fake_card` returns a safe processing response with:

- provider `simulator`
- status `processing`
- generated `sim_*` provider reference

## Fake Manual Invoice

`fake_manual_invoice` returns:

- provider `manual`
- status `pending`
- generated `manual_*` provider reference

## Fake Refund

Refund returns a deterministic safe success shape with a generated simulator refund reference.

## Fake Status Lookup

Status lookup returns stable fake statuses for simulator references.

## Fake Webhook Verification

The adapter supports predictable test-only signature verification. It does not create a public webhook endpoint or claim real-provider signature compatibility.

## Expected Tests

- simulator charge mapping
- manual invoice mapping
- refund result
- status lookup
- valid/invalid fake webhook verification
- capability map
- no external-provider enablement

## Safe Metadata Rules

The simulator removes sensitive keys from webhook payloads and does not return raw card data, credentials, tokens, passwords, or private keys.

## Non-Goals

- real provider behavior
- live payment processing
- external webhooks
- provider SDK integration
