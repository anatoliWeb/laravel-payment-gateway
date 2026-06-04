# Provider Webhook Verification Template

## Inbound Provider Webhook

Document the future inbound callback boundary without creating routes during documentation preparation.

## Signature Verification

Verify the official provider signature algorithm, required headers, timestamp rules, and secret source before implementation.

Do not infer or copy unverified signature behavior.

## Event ID and Replay Prevention

Document:

- stable provider event identifier
- replay-detection storage
- accepted timestamp window
- duplicate event response behavior

## Internal Event Mapping

Map verified provider event types into stable internal payment events and statuses.

Unknown events should be handled safely without changing payment state.

## Duplicate Webhook Handling

Webhook processing must be idempotent. Repeated valid events must not duplicate payment transitions or side effects.

## Security Checklist

- verify signature before processing
- resolve the correct provider account
- avoid logging raw secrets or sensitive payloads
- sanitize stored payload summaries
- reject invalid/replayed events predictably
