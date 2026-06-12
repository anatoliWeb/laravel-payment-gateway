import { Injectable } from '@angular/core';
import { map } from 'rxjs';
import { ApiClientService } from '../../../api/services/api-client.service';
import type { ApiResponse } from '../../../api/models/api-response.model';
import type {
  BillingActivityLog,
  BillingAdminPayment,
  BillingAdminPaymentTransaction,
  BillingAdminActivityFilters,
  BillingInvoice,
  BillingInvoicePaymentPayload,
  BillingPayment,
  BillingPaymentMethod,
  BillingPaymentMethodPayload,
  BillingPaymentPreference,
  BillingPaymentPreferencesPayload,
  BillingPaymentPayload,
  BillingPortalError,
  BillingAdminFeatureOverride,
  BillingAdminIdempotencyKey,
  BillingAdminProviderAccount,
  BillingAdminRestriction,
  BillingSubscription,
  BillingAdminWallet,
  BillingWallet,
  BillingWalletAdjustmentPayload,
  BillingWalletTopUpPayload,
  BillingWalletTopUpResponse,
  BillingWalletTransaction,
  BillingWebhookDelivery,
} from '../models/billing.model';

@Injectable({ providedIn: 'root' })
export class BillingService {
  constructor(private readonly apiClient: ApiClientService) {}

  loadWallet() {
    return this.apiClient.get<BillingWallet>('/v1/billing/wallet').pipe(
      map((response: ApiResponse<BillingWallet>) => response.data ?? null),
    );
  }

  loadInvoice(invoiceId: number) {
    return this.apiClient.get<BillingInvoice>(`/v1/billing/invoices/${invoiceId}`).pipe(
      map((response: ApiResponse<BillingInvoice>) => response.data ?? null),
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

  loadSubscription(subscriptionId: number) {
    return this.apiClient.get<BillingSubscription>(`/v1/billing/subscriptions/${subscriptionId}`).pipe(
      map((response: ApiResponse<BillingSubscription>) => response.data ?? null),
    );
  }

  loadActivityLogs(filters: BillingAdminActivityFilters = {}, page = 1) {
    const params = Object.fromEntries(
      Object.entries({
        ...filters,
        page,
      }).filter(([, value]) => value !== null && value !== undefined && value !== ''),
    ) as Record<string, string | number | boolean>;

    return this.apiClient.get<BillingActivityLog[]>('/v1/activity', {
      params,
    }).pipe(
      map((response: ApiResponse<BillingActivityLog[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadWebhookDeliveries(paymentId: number, page = 1, perPage = 10) {
    return this.apiClient.get<BillingWebhookDelivery[]>(`/v1/billing/payments/${paymentId}/webhooks`, {
      params: { page, per_page: perPage },
    }).pipe(
      map((response: ApiResponse<BillingWebhookDelivery[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  retryWebhookDelivery(webhookDeliveryId: number) {
    return this.apiClient.post<BillingWebhookDelivery, Record<string, never>>(
      `/v1/billing/webhooks/${webhookDeliveryId}/retry`,
      {},
    ).pipe(
      map((response: ApiResponse<BillingWebhookDelivery>) => response.data ?? null),
    );
  }

  loadPaymentMethods() {
    return this.apiClient.get<BillingPaymentMethod[]>('/v1/billing/payment-methods').pipe(
      map((response: ApiResponse<BillingPaymentMethod[]>) => response.data ?? []),
    );
  }

  loadAdminPayments(page = 1, perPage = 10, filters: Record<string, string | number | null | undefined> = {}) {
    const params = Object.fromEntries(
      Object.entries({
        page,
        per_page: perPage,
        ...filters,
      }).filter(([, value]) => value !== null && value !== undefined && String(value) !== ''),
    ) as Record<string, string | number | boolean>;

    return this.apiClient.get<BillingAdminPayment[]>('/v1/billing/admin/payments', {
      params,
    }).pipe(
      map((response: ApiResponse<BillingAdminPayment[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadAdminPayment(paymentIdOrUuid: string | number) {
    return this.apiClient.get<BillingAdminPayment>(`/v1/billing/admin/payments/${paymentIdOrUuid}`).pipe(
      map((response: ApiResponse<BillingAdminPayment>) => response.data ?? null),
    );
  }

  loadAdminPaymentTransactions(paymentIdOrUuid: string | number, page = 1, perPage = 10) {
    return this.apiClient.get<BillingAdminPaymentTransaction[]>(`/v1/billing/admin/payments/${paymentIdOrUuid}/transactions`, {
      params: { page, per_page: perPage },
    }).pipe(
      map((response: ApiResponse<BillingAdminPaymentTransaction[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadAdminWallets(page = 1, perPage = 10, filters: Record<string, string | number | null | undefined> = {}) {
    const params = Object.fromEntries(
      Object.entries({
        page,
        per_page: perPage,
        ...filters,
      }).filter(([, value]) => value !== null && value !== undefined && String(value) !== ''),
    ) as Record<string, string | number | boolean>;

    return this.apiClient.get<BillingAdminWallet[]>('/v1/billing/admin/wallets', { params }).pipe(
      map((response: ApiResponse<BillingAdminWallet[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadAdminWallet(walletIdOrUuid: string | number) {
    return this.apiClient.get<BillingAdminWallet>(`/v1/billing/admin/wallets/${walletIdOrUuid}`).pipe(
      map((response: ApiResponse<BillingAdminWallet>) => response.data ?? null),
    );
  }

  loadAdminWalletTransactions(walletIdOrUuid: string | number, page = 1, perPage = 10) {
    return this.apiClient.get<BillingWalletTransaction[]>(`/v1/billing/admin/wallets/${walletIdOrUuid}/transactions`, {
      params: { page, per_page: perPage },
    }).pipe(
      map((response: ApiResponse<BillingWalletTransaction[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadAdminIdempotencyKeys(page = 1, perPage = 10, filters: Record<string, string | number | null | undefined> = {}) {
    const params = Object.fromEntries(
      Object.entries({
        page,
        per_page: perPage,
        ...filters,
      }).filter(([, value]) => value !== null && value !== undefined && String(value) !== ''),
    ) as Record<string, string | number | boolean>;

    return this.apiClient.get<BillingAdminIdempotencyKey[]>('/v1/billing/admin/idempotency-keys', { params }).pipe(
      map((response: ApiResponse<BillingAdminIdempotencyKey[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadAdminIdempotencyKey(idempotencyKeyId: string | number) {
    return this.apiClient.get<BillingAdminIdempotencyKey>(`/v1/billing/admin/idempotency-keys/${idempotencyKeyId}`).pipe(
      map((response: ApiResponse<BillingAdminIdempotencyKey>) => response.data ?? null),
    );
  }

  loadAdminProviderAccounts(page = 1, perPage = 10, filters: Record<string, string | number | null | undefined> = {}) {
    const params = Object.fromEntries(
      Object.entries({
        page,
        per_page: perPage,
        ...filters,
      }).filter(([, value]) => value !== null && value !== undefined && String(value) !== ''),
    ) as Record<string, string | number | boolean>;

    return this.apiClient.get<BillingAdminProviderAccount[]>('/v1/billing/admin/provider-accounts', { params }).pipe(
      map((response: ApiResponse<BillingAdminProviderAccount[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadAdminProviderAccount(providerAccountId: string | number) {
    return this.apiClient.get<BillingAdminProviderAccount>(`/v1/billing/admin/provider-accounts/${providerAccountId}`).pipe(
      map((response: ApiResponse<BillingAdminProviderAccount>) => response.data ?? null),
    );
  }

  loadAdminRestrictions(page = 1, perPage = 10, filters: Record<string, string | number | null | undefined> = {}) {
    const params = Object.fromEntries(
      Object.entries({
        page,
        per_page: perPage,
        ...filters,
      }).filter(([, value]) => value !== null && value !== undefined && String(value) !== ''),
    ) as Record<string, string | number | boolean>;

    return this.apiClient.get<BillingAdminRestriction[]>('/v1/billing/admin/restrictions', { params }).pipe(
      map((response: ApiResponse<BillingAdminRestriction[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadAdminRestriction(restrictionId: string | number) {
    return this.apiClient.get<BillingAdminRestriction>(`/v1/billing/admin/restrictions/${restrictionId}`).pipe(
      map((response: ApiResponse<BillingAdminRestriction>) => response.data ?? null),
    );
  }

  loadAdminFeatureOverrides(page = 1, perPage = 10, filters: Record<string, string | number | null | undefined> = {}) {
    const params = Object.fromEntries(
      Object.entries({
        page,
        per_page: perPage,
        ...filters,
      }).filter(([, value]) => value !== null && value !== undefined && String(value) !== ''),
    ) as Record<string, string | number | boolean>;

    return this.apiClient.get<BillingAdminFeatureOverride[]>('/v1/billing/admin/overrides', { params }).pipe(
      map((response: ApiResponse<BillingAdminFeatureOverride[]>) => ({
        items: Array.isArray(response.data) ? response.data : [],
        meta: response.meta ?? null,
      })),
    );
  }

  loadAdminFeatureOverride(featureOverrideId: string | number) {
    return this.apiClient.get<BillingAdminFeatureOverride>(`/v1/billing/admin/overrides/${featureOverrideId}`).pipe(
      map((response: ApiResponse<BillingAdminFeatureOverride>) => response.data ?? null),
    );
  }

  createPayment(payload: BillingPaymentPayload, idempotencyKey: string) {
    return this.apiClient.post<BillingPayment, BillingPaymentPayload>(
      '/v1/billing/payments',
      payload,
      {
        headers: {
          'Idempotency-Key': idempotencyKey,
        },
      },
    ).pipe(
      map((response: ApiResponse<BillingPayment>) => response.data ?? null),
    );
  }

  payInvoice(invoiceId: number, payload: BillingInvoicePaymentPayload, idempotencyKey: string) {
    return this.apiClient.post<BillingPayment, BillingInvoicePaymentPayload>(
      `/v1/billing/invoices/${invoiceId}/pay`,
      payload,
      {
        headers: {
          'Idempotency-Key': idempotencyKey,
        },
      },
    ).pipe(
      map((response: ApiResponse<BillingPayment>) => response.data ?? null),
    );
  }

  createWalletTopUp(payload: BillingWalletTopUpPayload, idempotencyKey: string) {
    return this.apiClient.post<BillingWalletTopUpResponse, BillingWalletTopUpPayload>(
      '/v1/billing/wallet/top-ups',
      payload,
      {
        headers: {
          'Idempotency-Key': idempotencyKey,
        },
      },
    ).pipe(
      map((response: ApiResponse<BillingWalletTopUpResponse>) => response.data ?? null),
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

  adjustWallet(payload: BillingWalletAdjustmentPayload, idempotencyKey: string) {
    return this.apiClient.post<BillingWalletTransaction, BillingWalletAdjustmentPayload>(
      '/v1/billing/wallet-adjustments',
      payload,
      {
        headers: {
          'Idempotency-Key': idempotencyKey,
        },
      },
    ).pipe(
      map((response: ApiResponse<BillingWalletTransaction>) => response.data ?? null),
    );
  }

  simulatePaymentSuccess(paymentIdOrUuid: string | number) {
    return this.apiClient.post<BillingPayment, Record<string, never>>(
      `/v1/billing/payments/${paymentIdOrUuid}/simulate/success`,
      {},
    ).pipe(
      map((response: ApiResponse<BillingPayment>) => response.data ?? null),
    );
  }

  simulatePaymentFailure(paymentIdOrUuid: string | number, reason = 'card_declined') {
    return this.apiClient.post<BillingPayment, { reason: string; metadata: Record<string, unknown> }>(
      `/v1/billing/payments/${paymentIdOrUuid}/simulate/failure`,
      {
        reason,
        metadata: {
          scenario: 'demo_checkout',
        },
      },
    ).pipe(
      map((response: ApiResponse<BillingPayment>) => response.data ?? null),
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
        errors: maybeError.errors ?? null,
      };
    }

    return {
      status: null,
      code: null,
      message: 'Request failed.',
      errors: null,
    };
  }

  static describeErrorFields(errors: unknown): string[] {
    if (!errors || typeof errors !== 'object') {
      return [];
    }

    return Object.entries(errors as Record<string, unknown>).map(([field, value]) => {
      if (Array.isArray(value)) {
        return `${field}: ${value.map((entry) => String(entry)).join(', ')}`;
      }

      if (value && typeof value === 'object') {
        return `${field}: ${JSON.stringify(value)}`;
      }

      return `${field}: ${String(value)}`;
    });
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
