# Provider Capabilities Template

## Purpose

Record verified provider capabilities before enabling an adapter.

| Capability | Supported | Verification Notes |
| --- | --- | --- |
| `supportsCharge` | unknown | Verify official charge/payment documentation. |
| `supportsRefund` | unknown | Verify refund rules and limitations. |
| `supportsStatusLookup` | unknown | Verify payment status lookup behavior. |
| `supportsWebhookVerification` | unknown | Verify official callback/webhook security documentation. |
| `supportsManualInvoice` | unknown | Verify whether an equivalent flow exists. |
| `supportsRedirect` | unknown | Verify redirect/hosted-payment requirements. |
| `supportsTokenizedCard` | unknown | Verify tokenization and credential-handling requirements. |

Unknown capability values must not be presented as supported until official documentation and adapter tests confirm them.
