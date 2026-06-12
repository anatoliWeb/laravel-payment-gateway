import { Injectable } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class BillingIdempotencyService {
  // WHY:
  // Client-side idempotency keys protect billing writes from duplicate submits
  // and retry storms in local/dev browser workflows.
  createKey(prefix = 'billing'): string {
    const token = typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;

    return `${prefix}-${token}`;
  }
}
