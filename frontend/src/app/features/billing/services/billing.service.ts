import { Injectable } from '@angular/core';
import { map } from 'rxjs';
import { ApiClientService } from '../../../api/services/api-client.service';
import type { ApiResponse } from '../../../api/models/api-response.model';
import type {
  BillingInvoice,
  BillingPaymentMethod,
  BillingPaymentMethodPayload,
  BillingPaymentPreference,
  BillingPaymentPreferencesPayload,
  BillingPortalError,
  BillingWallet,
  BillingWalletTransaction,
} from '../models/billing.model';

@Injectable({ providedIn: 'root' })
export class BillingService {
  constructor(private readonly apiClient: ApiClientService) {}

  loadWallet() {
    return this.apiClient.get<BillingWallet>('/v1/billing/wallet').pipe(
      map((response: ApiResponse<BillingWallet>) => response.data ?? null),
    );
  }

  loadWalletTransactions(page = 1, perPage = 10) {
    return this.apiClient.get<BillingWalletTransaction[]>('/v1/billing/wallet/transactions', {
      params: { page, per_page: perPage },
    }).pipe(
      map((response: ApiResponse<BillingWalletTransaction[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadInvoices(page = 1, perPage = 5) {
    return this.apiClient.get<BillingInvoice[]>('/v1/billing/invoices', {
      params: { page, per_page: perPage },
    }).pipe(
      map((response: ApiResponse<BillingInvoice[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadPaymentMethods() {
    return this.apiClient.get<BillingPaymentMethod[]>('/v1/billing/payment-methods').pipe(
      map((response: ApiResponse<BillingPaymentMethod[]>) => response.data ?? []),
    );
  }

  createPaymentMethod(payload: BillingPaymentMethodPayload) {
    return this.apiClient.post<BillingPaymentMethod, BillingPaymentMethodPayload>('/v1/billing/payment-methods', payload).pipe(
      map((response: ApiResponse<BillingPaymentMethod>) => response.data ?? null),
    );
  }

  setDefaultPaymentMethod(paymentMethodId: number) {
    return this.apiClient.post<BillingPaymentMethod, Record<string, never>>(
      `/v1/billing/payment-methods/${paymentMethodId}/set-default`,
      {},
    ).pipe(
      map((response: ApiResponse<BillingPaymentMethod>) => response.data ?? null),
    );
  }

  deactivatePaymentMethod(paymentMethodId: number) {
    return this.apiClient.delete<BillingPaymentMethod>(`/v1/billing/payment-methods/${paymentMethodId}`).pipe(
      map((response: ApiResponse<BillingPaymentMethod>) => response.data ?? null),
    );
  }

  loadPaymentPreferences() {
    return this.apiClient.get<BillingPaymentPreference>('/v1/billing/payment-preferences').pipe(
      map((response: ApiResponse<BillingPaymentPreference>) => response.data ?? null),
    );
  }

  updatePaymentPreferences(payload: BillingPaymentPreferencesPayload) {
    return this.apiClient.patch<BillingPaymentPreference, BillingPaymentPreferencesPayload>(
      '/v1/billing/payment-preferences',
      payload,
    ).pipe(
      map((response: ApiResponse<BillingPaymentPreference>) => response.data ?? null),
    );
  }

  static extractError(error: unknown): BillingPortalError {
    if (error && typeof error === 'object') {
      const maybeError = error as {
        status?: number;
        code?: string;
        message?: string;
        errors?: unknown;
      };

      const stableCode = this.extractStableCode(maybeError);

      return {
        status: typeof maybeError.status === 'number' ? maybeError.status : null,
        code: stableCode,
        message: typeof maybeError.message === 'string' && maybeError.message.trim() !== ''
          ? maybeError.message
          : 'Request failed.',
      };
    }

    return {
      status: null,
      code: null,
      message: 'Request failed.',
    };
  }

  private static extractStableCode(error: { code?: string; errors?: unknown }): string | null {
    if (typeof error.code === 'string' && error.code.trim() !== '') {
      return error.code;
    }

    if (error.errors && typeof error.errors === 'object' && error.errors !== null) {
      const maybeErrors = error.errors as { code?: unknown };
      if (typeof maybeErrors.code === 'string' && maybeErrors.code.trim() !== '') {
        return maybeErrors.code;
      }
    }

    return null;
  }
}
