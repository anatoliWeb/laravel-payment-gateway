import { Component, OnInit, inject } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { BillingIdempotencyService } from '../../../billing/services/billing-idempotency.service';
import { BillingService } from '../../../billing/services/billing.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import type {
  BillingActivityLog as AdminBillingActivityLog,
  BillingAdminFeatureOverride,
  BillingAdminIdempotencyKey,
  BillingAdminPayment,
  BillingAdminPaymentTransaction,
  BillingAdminActivityFilters,
  BillingInvoice,
  BillingPaginationMeta,
  BillingPortalError,
  BillingAdminProviderAccount,
  BillingAdminRestriction,
  BillingSubscription,
  BillingWebhookDelivery,
  BillingAdminWallet,
  BillingWalletAdjustmentPayload,
  BillingWalletTransaction,
} from '../../../billing/models/billing.model';

type DashboardGap = {
  title: string;
  note: string;
  status: 'gap' | 'ready' | 'permission';
};

@Component({
  selector: 'app-admin-billing-dashboard-page',
  templateUrl: './admin-billing-dashboard-page.component.html',
  styleUrls: ['./admin-billing-dashboard-page.component.scss'],
  standalone: false,
})
export class AdminBillingDashboardPageComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly idempotency = inject(BillingIdempotencyService);
  private readonly permissionService = inject(PermissionService);

  readonly invoicePageSize = 6;
  readonly paymentPageSize = 8;
  readonly activityPageSize = 8;
  readonly webhookPageSize = 8;

  readonly gapCards: DashboardGap[] = [
    {
      title: 'Idempotency records',
      note: 'Admin operators can review fingerprints, response status, and lifecycle timestamps without exposing raw keys.',
      status: 'ready',
    },
    {
      title: 'Provider accounts',
      note: 'Provider account state, capabilities, and masked credentials are now browseable through read-only endpoints.',
      status: 'ready',
    },
    {
      title: 'Restrictions / blacklist',
      note: 'Restriction records are available for operator review while write actions stay outside the admin dashboard.',
      status: 'ready',
    },
    {
      title: 'Feature overrides',
      note: 'Feature overrides can be inspected as operational data without allowing the UI to change billing limits.',
      status: 'ready',
    },
  ];

  invoices: BillingInvoice[] = [];
  invoicesMeta: BillingPaginationMeta | null = null;
  invoicesLoading = false;
  invoicesError: BillingPortalError | null = null;
  selectedInvoice: BillingInvoice | null = null;
  selectedInvoiceLoading = false;
  selectedInvoiceError: BillingPortalError | null = null;

  payments: BillingAdminPayment[] = [];
  paymentsMeta: BillingPaginationMeta | null = null;
  paymentsLoading = false;
  paymentsError: BillingPortalError | null = null;
  selectedPayment: BillingAdminPayment | null = null;
  selectedPaymentLoading = false;
  selectedPaymentError: BillingPortalError | null = null;
  selectedPaymentTransactions: BillingAdminPaymentTransaction[] = [];
  selectedPaymentTransactionsMeta: BillingPaginationMeta | null = null;
  selectedPaymentTransactionsLoading = false;
  selectedPaymentTransactionsError: BillingPortalError | null = null;

  activityLogs: AdminBillingActivityLog[] = [];
  activityMeta: BillingPaginationMeta | null = null;
  activityLoading = false;
  activityError: BillingPortalError | null = null;

  webhookDeliveries: BillingWebhookDelivery[] = [];
  webhookMeta: BillingPaginationMeta | null = null;
  webhookLoading = false;
  webhookError: BillingPortalError | null = null;
  webhookRetryMessage: string | null = null;
  webhookRetryError: BillingPortalError | null = null;

  subscription: BillingSubscription | null = null;
  subscriptionLoading = false;
  subscriptionError: BillingPortalError | null = null;

  wallets: BillingAdminWallet[] = [];
  walletsMeta: BillingPaginationMeta | null = null;
  walletsLoading = false;
  walletsError: BillingPortalError | null = null;
  selectedWallet: BillingAdminWallet | null = null;
  selectedWalletLoading = false;
  selectedWalletError: BillingPortalError | null = null;
  selectedWalletTransactions: BillingWalletTransaction[] = [];
  selectedWalletTransactionsMeta: BillingPaginationMeta | null = null;
  selectedWalletTransactionsLoading = false;
  selectedWalletTransactionsError: BillingPortalError | null = null;

  idempotencyKeys: BillingAdminIdempotencyKey[] = [];
  idempotencyKeysMeta: BillingPaginationMeta | null = null;
  idempotencyKeysLoading = false;
  idempotencyKeysError: BillingPortalError | null = null;
  selectedIdempotencyKey: BillingAdminIdempotencyKey | null = null;
  selectedIdempotencyKeyLoading = false;
  selectedIdempotencyKeyError: BillingPortalError | null = null;

  providerAccounts: BillingAdminProviderAccount[] = [];
  providerAccountsMeta: BillingPaginationMeta | null = null;
  providerAccountsLoading = false;
  providerAccountsError: BillingPortalError | null = null;
  selectedProviderAccount: BillingAdminProviderAccount | null = null;
  selectedProviderAccountLoading = false;
  selectedProviderAccountError: BillingPortalError | null = null;

  restrictions: BillingAdminRestriction[] = [];
  restrictionsMeta: BillingPaginationMeta | null = null;
  restrictionsLoading = false;
  restrictionsError: BillingPortalError | null = null;
  selectedRestriction: BillingAdminRestriction | null = null;
  selectedRestrictionLoading = false;
  selectedRestrictionError: BillingPortalError | null = null;

  featureOverrides: BillingAdminFeatureOverride[] = [];
  featureOverridesMeta: BillingPaginationMeta | null = null;
  featureOverridesLoading = false;
  featureOverridesError: BillingPortalError | null = null;
  selectedFeatureOverride: BillingAdminFeatureOverride | null = null;
  selectedFeatureOverrideLoading = false;
  selectedFeatureOverrideError: BillingPortalError | null = null;

  walletAdjustmentLoading = false;
  walletAdjustmentMessage: string | null = null;
  walletAdjustmentError: BillingPortalError | null = null;
  walletAdjustmentSuccess: BillingWalletTransaction | null = null;

  readonly walletAdjustmentForm = this.fb.group({
    user_id: ['', [Validators.required, Validators.min(1)]],
    currency: ['USD', [Validators.required, Validators.minLength(3), Validators.maxLength(3)]],
    amount: ['', [Validators.required, Validators.min(1)]],
    direction: ['credit' as 'credit' | 'debit', [Validators.required]],
    reason: ['', [Validators.required, Validators.maxLength(255)]],
    description: ['Manual adjustment from admin billing UI', [Validators.maxLength(255)]],
    reference: [''],
  });

  readonly subscriptionLookupForm = this.fb.group({
    subscription_id: ['', [Validators.required, Validators.min(1)]],
  });

  readonly webhookLookupForm = this.fb.group({
    payment_id: ['', [Validators.required, Validators.min(1)]],
  });

  readonly activityFiltersForm = this.fb.group({
    search: ['billing'],
    action: [''],
    user_id: [''],
    subject_type: [''],
    model: [''],
    date_from: [''],
    date_to: [''],
  });

  constructor(private readonly billingService: BillingService) {}

  ngOnInit(): void {
    setTimeout(() => {
      void this.refresh();
    });
  }

  get isAdmin(): boolean {
    return this.permissionService.hasRole('admin');
  }

  get canAdjustWallet(): boolean {
    return this.isAdmin
      || this.permissionService.hasPermission('billing.wallets.adjust')
      || this.permissionService.hasPermission('billing.wallets.credit')
      || this.permissionService.hasPermission('billing.wallets.debit');
  }

  get canRetryWebhooks(): boolean {
    return this.isAdmin || this.permissionService.hasPermission('billing.webhooks.retry');
  }

  get invoicesCount(): number {
    return this.invoicesMeta?.total ?? this.invoices.length;
  }

  get paymentsCount(): number {
    return this.paymentsMeta?.total ?? this.payments.length;
  }

  get activityCount(): number {
    return this.activityMeta?.total ?? this.activityLogs.length;
  }

  get webhookCount(): number {
    return this.webhookMeta?.total ?? this.webhookDeliveries.length;
  }

  get walletCount(): number {
    return this.walletsMeta?.total ?? this.wallets.length;
  }

  get idempotencyCount(): number {
    return this.idempotencyKeysMeta?.total ?? this.idempotencyKeys.length;
  }

  get providerAccountCount(): number {
    return this.providerAccountsMeta?.total ?? this.providerAccounts.length;
  }

  get summaryCards(): Array<{ label: string; value: string; hint: string }> {
    return [
      {
        label: 'Invoices',
        value: String(this.invoicesCount),
        hint: this.invoicesLoading ? 'Loading invoice list' : 'Admin-readable invoices',
      },
      {
        label: 'Payments',
        value: String(this.paymentsCount),
        hint: this.paymentsLoading ? 'Loading payment list' : 'UUID-backed admin payment view',
      },
      {
        label: 'Activity logs',
        value: String(this.activityCount),
        hint: this.activityLoading ? 'Loading audit trail' : 'Billing and platform audit trail',
      },
      {
        label: 'Webhook deliveries',
        value: String(this.webhookCount),
        hint: this.webhookLoading ? 'Loading deliveries' : 'Lookup by payment id',
      },
      {
        label: 'Wallet adjustments',
        value: this.canAdjustWallet ? 'Allowed' : 'Locked',
        hint: this.canAdjustWallet ? 'Permission-gated UI' : 'Admin permission required',
      },
      {
        label: 'Wallets',
        value: String(this.walletCount),
        hint: this.walletsLoading ? 'Loading wallets' : 'Admin wallet balances and history',
      },
      {
        label: 'Idempotency',
        value: String(this.idempotencyCount),
        hint: this.idempotencyKeysLoading ? 'Loading keys' : 'Key fingerprint lookup only',
      },
      {
        label: 'Provider accounts',
        value: String(this.providerAccountCount),
        hint: this.providerAccountsLoading ? 'Loading accounts' : 'Masked configuration only',
      },
    ];
  }

  async refresh(): Promise<void> {
    await Promise.allSettled([
      this.loadPayments(1),
      this.loadInvoices(1),
      this.loadActivityLogs(1),
      this.loadWallets(1),
      this.loadIdempotencyKeys(1),
      this.loadProviderAccounts(1),
      this.loadRestrictions(1),
      this.loadFeatureOverrides(1),
    ]);
  }

  async loadPayments(page = 1): Promise<void> {
    this.paymentsLoading = true;
    this.paymentsError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadAdminPayments(page, this.paymentPageSize));
      this.payments = response.items;
      this.paymentsMeta = this.normalizeMeta(response.meta, this.paymentPageSize);
    } catch (error) {
      this.payments = [];
      this.paymentsMeta = null;
      this.paymentsError = BillingService.extractError(error);
    } finally {
      this.paymentsLoading = false;
    }
  }

  async inspectPayment(payment: BillingAdminPayment): Promise<void> {
    this.selectedPayment = payment;
    this.selectedPaymentError = null;
    this.selectedPaymentLoading = true;
    this.selectedPaymentTransactionsError = null;

    try {
      this.selectedPayment = await firstValueFrom(this.billingService.loadAdminPayment(payment.uuid));
      await this.loadPaymentTransactions(payment.uuid, 1);
    } catch (error) {
      this.selectedPaymentError = BillingService.extractError(error);
      this.selectedPaymentTransactions = [];
      this.selectedPaymentTransactionsMeta = null;
    } finally {
      this.selectedPaymentLoading = false;
    }
  }

  async loadInvoices(page = 1): Promise<void> {
    this.invoicesLoading = true;
    this.invoicesError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadInvoices(page, this.invoicePageSize));
      this.invoices = response.items;
      this.invoicesMeta = this.normalizeMeta(response.meta);
    } catch (error) {
      this.invoices = [];
      this.invoicesMeta = null;
      this.invoicesError = BillingService.extractError(error);
    } finally {
      this.invoicesLoading = false;
    }
  }

  async inspectInvoice(invoice: BillingInvoice): Promise<void> {
    this.selectedInvoice = invoice;
    this.selectedInvoiceError = null;
    this.selectedInvoiceLoading = true;

    try {
      this.selectedInvoice = await firstValueFrom(this.billingService.loadInvoice(invoice.id));
    } catch (error) {
      this.selectedInvoiceError = BillingService.extractError(error);
    } finally {
      this.selectedInvoiceLoading = false;
    }
  }

  async lookupSubscription(): Promise<void> {
    this.subscriptionError = null;

    if (this.subscriptionLookupForm.invalid) {
      this.subscriptionLookupForm.markAllAsTouched();
      this.subscriptionError = {
        status: 422,
        code: 'validation',
        message: 'Please enter a subscription id.',
        errors: null,
      };
      return;
    }

    const subscriptionId = Number(this.subscriptionLookupForm.controls.subscription_id.value);
    this.subscriptionLoading = true;

    try {
      this.subscription = await firstValueFrom(this.billingService.loadSubscription(subscriptionId));
    } catch (error) {
      this.subscription = null;
      this.subscriptionError = BillingService.extractError(error);
    } finally {
      this.subscriptionLoading = false;
    }
  }

  async loadActivityLogs(page = 1): Promise<void> {
    this.activityLoading = true;
    this.activityError = null;

    try {
      const filters = this.activityFiltersForm.getRawValue();
      const payload: BillingAdminActivityFilters = {
        per_page: this.activityPageSize,
        search: filters.search || 'billing',
        action: filters.action || undefined,
        user_id: this.parseOptionalInteger(filters.user_id),
        subject_type: filters.subject_type || undefined,
        model: filters.model || undefined,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
      };

      const response = await firstValueFrom(this.billingService.loadActivityLogs({
        ...payload,
        per_page: this.activityPageSize,
      }, page));

      this.activityLogs = response.items;
      this.activityMeta = this.normalizeMeta(response.meta);
    } catch (error) {
      this.activityLogs = [];
      this.activityMeta = null;
      this.activityError = BillingService.extractError(error);
    } finally {
      this.activityLoading = false;
    }
  }

  async loadPaymentTransactions(paymentIdOrUuid: string, page = 1): Promise<void> {
    this.selectedPaymentTransactionsLoading = true;
    this.selectedPaymentTransactionsError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadAdminPaymentTransactions(paymentIdOrUuid, page, this.paymentPageSize));
      this.selectedPaymentTransactions = response.items;
      this.selectedPaymentTransactionsMeta = this.normalizeMeta(response.meta, this.paymentPageSize);
    } catch (error) {
      this.selectedPaymentTransactions = [];
      this.selectedPaymentTransactionsMeta = null;
      this.selectedPaymentTransactionsError = BillingService.extractError(error);
    } finally {
      this.selectedPaymentTransactionsLoading = false;
    }
  }

  async loadWallets(page = 1): Promise<void> {
    this.walletsLoading = true;
    this.walletsError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadAdminWallets(page, this.paymentPageSize));
      this.wallets = response.items;
      this.walletsMeta = this.normalizeMeta(response.meta, this.paymentPageSize);
    } catch (error) {
      this.wallets = [];
      this.walletsMeta = null;
      this.walletsError = BillingService.extractError(error);
    } finally {
      this.walletsLoading = false;
    }
  }

  async inspectWallet(wallet: BillingAdminWallet): Promise<void> {
    this.selectedWallet = wallet;
    this.selectedWalletError = null;
    this.selectedWalletLoading = true;
    this.selectedWalletTransactionsError = null;

    try {
      this.selectedWallet = await firstValueFrom(this.billingService.loadAdminWallet(wallet.id));
      await this.loadWalletTransactions(wallet.id, 1);
    } catch (error) {
      this.selectedWalletError = BillingService.extractError(error);
      this.selectedWalletTransactions = [];
      this.selectedWalletTransactionsMeta = null;
    } finally {
      this.selectedWalletLoading = false;
    }
  }

  async loadWalletTransactions(walletIdOrUuid: string | number, page = 1): Promise<void> {
    this.selectedWalletTransactionsLoading = true;
    this.selectedWalletTransactionsError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadAdminWalletTransactions(walletIdOrUuid, page, this.paymentPageSize));
      this.selectedWalletTransactions = response.items;
      this.selectedWalletTransactionsMeta = this.normalizeMeta(response.meta, this.paymentPageSize);
    } catch (error) {
      this.selectedWalletTransactions = [];
      this.selectedWalletTransactionsMeta = null;
      this.selectedWalletTransactionsError = BillingService.extractError(error);
    } finally {
      this.selectedWalletTransactionsLoading = false;
    }
  }

  async loadIdempotencyKeys(page = 1): Promise<void> {
    this.idempotencyKeysLoading = true;
    this.idempotencyKeysError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadAdminIdempotencyKeys(page, this.paymentPageSize));
      this.idempotencyKeys = response.items;
      this.idempotencyKeysMeta = this.normalizeMeta(response.meta, this.paymentPageSize);
    } catch (error) {
      this.idempotencyKeys = [];
      this.idempotencyKeysMeta = null;
      this.idempotencyKeysError = BillingService.extractError(error);
    } finally {
      this.idempotencyKeysLoading = false;
    }
  }

  async inspectIdempotencyKey(idempotencyKey: BillingAdminIdempotencyKey): Promise<void> {
    this.selectedIdempotencyKey = idempotencyKey;
    this.selectedIdempotencyKeyError = null;
    this.selectedIdempotencyKeyLoading = true;

    try {
      this.selectedIdempotencyKey = await firstValueFrom(this.billingService.loadAdminIdempotencyKey(idempotencyKey.id));
    } catch (error) {
      this.selectedIdempotencyKeyError = BillingService.extractError(error);
    } finally {
      this.selectedIdempotencyKeyLoading = false;
    }
  }

  async loadProviderAccounts(page = 1): Promise<void> {
    this.providerAccountsLoading = true;
    this.providerAccountsError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadAdminProviderAccounts(page, this.paymentPageSize));
      this.providerAccounts = response.items;
      this.providerAccountsMeta = this.normalizeMeta(response.meta, this.paymentPageSize);
    } catch (error) {
      this.providerAccounts = [];
      this.providerAccountsMeta = null;
      this.providerAccountsError = BillingService.extractError(error);
    } finally {
      this.providerAccountsLoading = false;
    }
  }

  async inspectProviderAccount(providerAccount: BillingAdminProviderAccount): Promise<void> {
    this.selectedProviderAccount = providerAccount;
    this.selectedProviderAccountError = null;
    this.selectedProviderAccountLoading = true;

    try {
      this.selectedProviderAccount = await firstValueFrom(this.billingService.loadAdminProviderAccount(providerAccount.uuid));
    } catch (error) {
      this.selectedProviderAccountError = BillingService.extractError(error);
    } finally {
      this.selectedProviderAccountLoading = false;
    }
  }

  async loadRestrictions(page = 1): Promise<void> {
    this.restrictionsLoading = true;
    this.restrictionsError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadAdminRestrictions(page, this.paymentPageSize));
      this.restrictions = response.items;
      this.restrictionsMeta = this.normalizeMeta(response.meta, this.paymentPageSize);
    } catch (error) {
      this.restrictions = [];
      this.restrictionsMeta = null;
      this.restrictionsError = BillingService.extractError(error);
    } finally {
      this.restrictionsLoading = false;
    }
  }

  async inspectRestriction(restriction: BillingAdminRestriction): Promise<void> {
    this.selectedRestriction = restriction;
    this.selectedRestrictionError = null;
    this.selectedRestrictionLoading = true;

    try {
      this.selectedRestriction = await firstValueFrom(this.billingService.loadAdminRestriction(restriction.id));
    } catch (error) {
      this.selectedRestrictionError = BillingService.extractError(error);
    } finally {
      this.selectedRestrictionLoading = false;
    }
  }

  async loadFeatureOverrides(page = 1): Promise<void> {
    this.featureOverridesLoading = true;
    this.featureOverridesError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadAdminFeatureOverrides(page, this.paymentPageSize));
      this.featureOverrides = response.items;
      this.featureOverridesMeta = this.normalizeMeta(response.meta, this.paymentPageSize);
    } catch (error) {
      this.featureOverrides = [];
      this.featureOverridesMeta = null;
      this.featureOverridesError = BillingService.extractError(error);
    } finally {
      this.featureOverridesLoading = false;
    }
  }

  async inspectFeatureOverride(featureOverride: BillingAdminFeatureOverride): Promise<void> {
    this.selectedFeatureOverride = featureOverride;
    this.selectedFeatureOverrideError = null;
    this.selectedFeatureOverrideLoading = true;

    try {
      this.selectedFeatureOverride = await firstValueFrom(this.billingService.loadAdminFeatureOverride(featureOverride.id));
    } catch (error) {
      this.selectedFeatureOverrideError = BillingService.extractError(error);
    } finally {
      this.selectedFeatureOverrideLoading = false;
    }
  }

  async lookupWebhookDeliveries(page = 1): Promise<void> {
    this.webhookError = null;
    this.webhookRetryMessage = null;
    this.webhookRetryError = null;

    if (this.webhookLookupForm.invalid) {
      this.webhookLookupForm.markAllAsTouched();
      this.webhookError = {
        status: 422,
        code: 'validation',
        message: 'Please enter a payment id.',
        errors: null,
      };
      return;
    }

    const paymentId = Number(this.webhookLookupForm.controls.payment_id.value);
    this.webhookLoading = true;

    try {
      const response = await firstValueFrom(this.billingService.loadWebhookDeliveries(paymentId, page, this.webhookPageSize));
      this.webhookDeliveries = response.items;
      this.webhookMeta = this.normalizeMeta(response.meta);
    } catch (error) {
      this.webhookDeliveries = [];
      this.webhookMeta = null;
      this.webhookError = BillingService.extractError(error);
    } finally {
      this.webhookLoading = false;
    }
  }

  async retryWebhook(delivery: BillingWebhookDelivery): Promise<void> {
    if (!this.canRetryWebhooks) {
      this.webhookRetryError = {
        status: 403,
        code: 'forbidden',
        message: 'Webhook retry is permission gated.',
        errors: null,
      };
      return;
    }

    this.webhookRetryError = null;
    this.webhookRetryMessage = null;

    try {
      const retried = await firstValueFrom(this.billingService.retryWebhookDelivery(delivery.id));
      await this.lookupWebhookDeliveries(this.webhookMeta?.current_page ?? 1);
      this.webhookRetryMessage = `Webhook delivery ${retried?.uuid ?? delivery.uuid} queued for retry.`;
    } catch (error) {
      this.webhookRetryError = BillingService.extractError(error);
    }
  }

  async submitWalletAdjustment(): Promise<void> {
    this.walletAdjustmentError = null;
    this.walletAdjustmentMessage = null;

    if (!this.canAdjustWallet) {
      this.walletAdjustmentError = {
        status: 403,
        code: 'forbidden',
        message: 'Wallet adjustments require billing wallet permissions.',
        errors: null,
      };
      return;
    }

    if (this.walletAdjustmentForm.invalid) {
      this.walletAdjustmentForm.markAllAsTouched();
      this.walletAdjustmentError = {
        status: 422,
        code: 'validation',
        message: 'Please complete the wallet adjustment form.',
        errors: null,
      };
      return;
    }

    const raw = this.walletAdjustmentForm.getRawValue();
    const idempotencyKey = this.idempotency.createKey('wallet-adjustment');
    const payload: BillingWalletAdjustmentPayload = {
      user_id: Number(raw.user_id),
      currency: String(raw.currency ?? 'USD').trim().toUpperCase() || 'USD',
      amount: Number(raw.amount),
      direction: raw.direction as 'credit' | 'debit',
      reason: String(raw.reason ?? '').trim(),
      description: String(raw.description ?? '').trim() || null,
      reference: String(raw.reference ?? '').trim() || null,
      metadata: {
        source: 'admin_billing_ui',
        surface: 'operator_dashboard',
      },
    };

    this.walletAdjustmentLoading = true;
    try {
      this.walletAdjustmentSuccess = await firstValueFrom(this.billingService.adjustWallet(payload, idempotencyKey));
      this.walletAdjustmentMessage = 'Wallet adjustment completed successfully.';
    } catch (error) {
      this.walletAdjustmentSuccess = null;
      this.walletAdjustmentError = BillingService.extractError(error);
    } finally {
      this.walletAdjustmentLoading = false;
    }
  }

  trackByInvoiceId(_: number, invoice: BillingInvoice): number {
    return invoice.id;
  }

  trackByActivityId(_: number, item: AdminBillingActivityLog): number {
    return item.id;
  }

  trackByPaymentId(_: number, item: BillingAdminPayment): number {
    return item.id;
  }

  trackByPaymentTransactionId(_: number, item: BillingAdminPaymentTransaction): number {
    return item.id;
  }

  trackByWebhookId(_: number, item: BillingWebhookDelivery): number {
    return item.id;
  }

  trackByWalletId(_: number, item: BillingAdminWallet): number {
    return item.id;
  }

  trackByIdempotencyId(_: number, item: BillingAdminIdempotencyKey): number {
    return item.id;
  }

  trackByProviderAccountId(_: number, item: BillingAdminProviderAccount): number {
    return item.id;
  }

  trackByRestrictionId(_: number, item: BillingAdminRestriction): number {
    return item.id;
  }

  trackByFeatureOverrideId(_: number, item: BillingAdminFeatureOverride): number {
    return item.id;
  }

  formatAmount(amount: number | null | undefined, currencyCode = 'USD', precision = 2): string {
    if (amount === null || amount === undefined) {
      return '-';
    }

    const normalized = amount / Math.pow(10, precision || 2);

    try {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: precision || 2,
        maximumFractionDigits: precision || 2,
      }).format(normalized) + ` ${currencyCode}`;
    } catch {
      return `${normalized.toFixed(precision || 2)} ${currencyCode}`;
    }
  }

  statusClass(status: string | null | undefined): string {
    const normalized = String(status ?? '').toLowerCase();

    if (['active', 'succeeded', 'paid', 'completed'].includes(normalized)) {
      return 'status--positive';
    }

    if (['pending', 'processing', 'payment_pending', 'queued'].includes(normalized)) {
      return 'status--pending';
    }

    if (['failed', 'expired', 'cancelled', 'inactive', 'void', 'past_due', 'error'].includes(normalized)) {
      return 'status--negative';
    }

    return 'status--neutral';
  }

  retryButtonLabel(delivery: BillingWebhookDelivery): string {
    if (delivery.status === 'delivered') {
      return 'Delivered';
    }

    return 'Retry';
  }

  canRetry(delivery: BillingWebhookDelivery): boolean {
    return this.canRetryWebhooks && ['failed', 'retrying', 'permanently_failed'].includes(String(delivery.status ?? '').toLowerCase());
  }

  errorFieldLines(error: BillingPortalError | null): string[] {
    return BillingService.describeErrorFields(error?.errors ?? null);
  }

  private parseOptionalInteger(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
      return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  private normalizeMeta(meta: unknown, perPage = this.invoicePageSize): BillingPaginationMeta | null {
    if (!meta || typeof meta !== 'object') {
      return null;
    }

    const source = meta as Record<string, unknown>;
    return {
      current_page: Number(source['current_page'] ?? 1),
      last_page: Number(source['last_page'] ?? 1),
      per_page: Number(source['per_page'] ?? perPage),
      total: Number(source['total'] ?? 0),
    };
  }
}
