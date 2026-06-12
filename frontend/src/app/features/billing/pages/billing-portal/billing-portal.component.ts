import { Component, inject, OnInit } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import {
  BillingService,
} from '../../services/billing.service';
import type {
  BillingInvoice,
  BillingPaymentMethod,
  BillingPaymentMethodPayload,
  BillingPaymentPreference,
  BillingPaymentPreferencesPayload,
  BillingPlanReference,
  BillingPortalError,
  BillingUsageReference,
  BillingWallet,
  BillingWalletBalance,
  BillingWalletTransaction,
} from '../../models/billing.model';

type PaginatedMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

@Component({
  selector: 'app-billing-portal',
  templateUrl: './billing-portal.component.html',
  styleUrls: ['./billing-portal.component.scss'],
  standalone: false,
})
export class BillingPortalComponent implements OnInit {
  readonly plannedPlans: BillingPlanReference[] = [
    {
      slug: 'free',
      name: 'Free',
      description: 'Onboarding and demo baseline.',
      priceLabel: 'Free',
      audience: 'New users',
      features: ['Limited chat usage', 'Basic dashboard access', 'No dialer access'],
    },
    {
      slug: 'basic',
      name: 'Basic',
      description: 'Entry paid tier for small teams.',
      priceLabel: 'Paid',
      audience: 'Small teams',
      features: ['Higher chat limits', 'Webhook-enabled workflows', 'Longer retention'],
    },
    {
      slug: 'pro',
      name: 'Pro',
      description: 'Production-like tier for growing teams.',
      priceLabel: 'Paid',
      audience: 'Growing teams',
      features: ['Advanced chat limits', 'API access', 'Dialer-ready feature keys'],
      highlighted: true,
    },
    {
      slug: 'enterprise',
      name: 'Enterprise',
      description: 'High-limit portfolio tier with custom controls.',
      priceLabel: 'Custom',
      audience: 'Large teams',
      features: ['Higher limits', 'Longer retention', 'Operator-friendly controls'],
    },
  ];

  readonly usageReference: BillingUsageReference[] = [
    { featureKey: 'chat.messages.daily', usedLabel: '-', limitLabel: '-', remainingLabel: '-', period: 'daily' },
    { featureKey: 'chat.messages.monthly', usedLabel: '-', limitLabel: '-', remainingLabel: '-', period: 'monthly' },
    { featureKey: 'chat.conversations.active', usedLabel: '-', limitLabel: '-', remainingLabel: '-', period: 'current' },
    { featureKey: 'chat.webhook_endpoints.count', usedLabel: '-', limitLabel: '-', remainingLabel: '-', period: 'current' },
    { featureKey: 'dialer.calls.monthly', usedLabel: '-', limitLabel: '-', remainingLabel: '-', period: 'monthly' },
    { featureKey: 'dialer.concurrent_calls', usedLabel: '-', limitLabel: '-', remainingLabel: '-', period: 'current' },
  ];

  wallet: BillingWallet | null = null;
  walletLoading = false;
  walletError: BillingPortalError | null = null;

  walletTransactions: BillingWalletTransaction[] = [];
  walletTransactionsMeta: PaginatedMeta | null = null;
  walletTransactionsLoading = false;
  walletTransactionsError: BillingPortalError | null = null;

  invoices: BillingInvoice[] = [];
  invoicesMeta: PaginatedMeta | null = null;
  invoicesLoading = false;
  invoicesError: BillingPortalError | null = null;

  paymentMethods: BillingPaymentMethod[] = [];
  paymentMethodsLoading = false;
  paymentMethodsError: BillingPortalError | null = null;
  paymentMethodActionError: BillingPortalError | null = null;
  paymentMethodActionMessage: string | null = null;

  paymentPreferences: BillingPaymentPreference | null = null;
  paymentPreferencesLoading = false;
  paymentPreferencesError: BillingPortalError | null = null;
  paymentPreferencesMessage: string | null = null;
  paymentPreferencesActionError: BillingPortalError | null = null;

  private readonly fb = inject(FormBuilder);

  readonly paymentMethodForm = this.fb.group({
    type: ['fake_card', [Validators.required]],
    brand: ['Visa', [Validators.required, Validators.maxLength(50)]],
    last4: ['4242', [Validators.required, Validators.minLength(4), Validators.maxLength(4)]],
    exp_month: ['12'],
    exp_year: ['2030'],
    display_name: ['Visa ending 4242', [Validators.required, Validators.maxLength(120)]],
  });

  readonly paymentPreferencesForm = this.fb.group({
    strategy: ['wallet_first', [Validators.required]],
    default_payment_method_id: [''],
    auto_charge_enabled: [true],
    auto_top_up_enabled: [false],
    auto_top_up_threshold_amount: ['500'],
    auto_top_up_amount: ['3000'],
    auto_top_up_currency: ['USD', [Validators.required, Validators.minLength(3), Validators.maxLength(3)]],
    max_auto_top_up_per_day: ['2'],
    max_auto_top_up_per_month: ['10'],
  });

  refreshing = false;
  missingApiNotice = 'This section is waiting for a dedicated billing endpoint.';

  constructor(
    private readonly billingService: BillingService,
  ) {}

  ngOnInit(): void {
    void this.refresh();
  }

  async refresh(): Promise<void> {
    this.refreshing = true;
    await Promise.allSettled([
      this.loadWallet(),
      this.loadWalletTransactions(),
      this.loadInvoices(),
      this.loadPaymentMethods(),
      this.loadPaymentPreferences(),
    ]);
    this.refreshing = false;
  }

  async createPaymentMethod(): Promise<void> {
    this.paymentMethodActionError = null;
    this.paymentMethodActionMessage = null;

    if (this.paymentMethodForm.invalid) {
      this.paymentMethodForm.markAllAsTouched();
      this.paymentMethodActionError = {
        status: 422,
        code: 'validation',
        message: 'Please complete the payment method form.',
      };
      return;
    }

    const raw = this.paymentMethodForm.getRawValue();
    const payload: BillingPaymentMethodPayload = {
      type: (raw.type ?? 'fake_card') as BillingPaymentMethodPayload['type'],
      brand: String(raw.brand ?? 'Visa').trim() || 'Visa',
      last4: String(raw.last4 ?? '4242').trim().slice(-4).padStart(4, '0'),
      exp_month: this.parseOptionalInteger(raw.exp_month),
      exp_year: this.parseOptionalInteger(raw.exp_year),
      display_name: String(raw.display_name ?? 'Demo payment method').trim() || 'Demo payment method',
      metadata: {
        source: 'billing_portal',
        simulator_safe: true,
      },
    };

    try {
      await firstValueFrom(this.billingService.createPaymentMethod(payload));
      this.paymentMethodActionMessage = 'Payment method created successfully.';
      this.paymentMethodForm.reset({
        type: 'fake_card',
        brand: 'Visa',
        last4: '4242',
        exp_month: '12',
        exp_year: '2030',
        display_name: 'Visa ending 4242',
      });
      await Promise.allSettled([
        this.loadPaymentMethods(),
        this.loadPaymentPreferences(),
      ]);
    } catch (error) {
      this.paymentMethodActionError = BillingService.extractError(error);
    }
  }

  async setDefaultPaymentMethod(paymentMethodId: number): Promise<void> {
    this.paymentMethodActionError = null;
    this.paymentMethodActionMessage = null;

    try {
      await firstValueFrom(this.billingService.setDefaultPaymentMethod(paymentMethodId));
      this.paymentMethodActionMessage = 'Default payment method updated.';
      await Promise.allSettled([
        this.loadPaymentMethods(),
        this.loadPaymentPreferences(),
      ]);
    } catch (error) {
      this.paymentMethodActionError = BillingService.extractError(error);
    }
  }

  async deactivatePaymentMethod(paymentMethodId: number): Promise<void> {
    this.paymentMethodActionError = null;
    this.paymentMethodActionMessage = null;

    try {
      await firstValueFrom(this.billingService.deactivatePaymentMethod(paymentMethodId));
      this.paymentMethodActionMessage = 'Payment method deactivated.';
      await Promise.allSettled([
        this.loadPaymentMethods(),
        this.loadPaymentPreferences(),
      ]);
    } catch (error) {
      this.paymentMethodActionError = BillingService.extractError(error);
    }
  }

  async savePaymentPreferences(): Promise<void> {
    this.paymentPreferencesActionError = null;
    this.paymentPreferencesMessage = null;

    if (this.paymentPreferencesForm.invalid) {
      this.paymentPreferencesForm.markAllAsTouched();
      this.paymentPreferencesActionError = {
        status: 422,
        code: 'validation',
        message: 'Please complete the payment preferences form.',
      };
      return;
    }

    const raw = this.paymentPreferencesForm.getRawValue();
    const payload: BillingPaymentPreferencesPayload = {
      strategy: raw.strategy as BillingPaymentPreferencesPayload['strategy'],
      default_payment_method_id: this.parseOptionalInteger(raw.default_payment_method_id),
      auto_charge_enabled: Boolean(raw.auto_charge_enabled),
      auto_top_up_enabled: Boolean(raw.auto_top_up_enabled),
      auto_top_up_threshold_amount: this.parseOptionalInteger(raw.auto_top_up_threshold_amount),
      auto_top_up_amount: this.parseOptionalInteger(raw.auto_top_up_amount),
      auto_top_up_currency: String(raw.auto_top_up_currency ?? 'USD').trim().toUpperCase() || 'USD',
      max_auto_top_up_per_day: this.parseOptionalInteger(raw.max_auto_top_up_per_day),
      max_auto_top_up_per_month: this.parseOptionalInteger(raw.max_auto_top_up_per_month),
    };

    try {
      await firstValueFrom(this.billingService.updatePaymentPreferences(payload));
      this.paymentPreferencesMessage = 'Payment preferences saved.';
      await this.loadPaymentPreferences();
    } catch (error) {
      this.paymentPreferencesActionError = BillingService.extractError(error);
    }
  }

  formatAmount(amount: number | null | undefined, currencyCode = 'USD', precision = 2): string {
    if (amount === null || amount === undefined) {
      return '-';
    }

    const divisor = Math.pow(10, precision || 2);
    const normalized = amount / divisor;

    try {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: precision || 2,
        maximumFractionDigits: precision || 2,
      }).format(normalized) + ` ${currencyCode}`;
    } catch {
      return `${normalized.toFixed(precision || 2)} ${currencyCode}`;
    }
  }

  formatDate(value: string | null | undefined): string {
    if (!value) {
      return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return date.toLocaleString();
  }

  statusClass(status: string | null | undefined): string {
    const normalized = String(status ?? '').toLowerCase();

    if (['active', 'succeeded', 'paid', 'completed'].includes(normalized)) {
      return 'status--positive';
    }

    if (['pending', 'processing', 'payment_pending'].includes(normalized)) {
      return 'status--pending';
    }

    if (['failed', 'expired', 'cancelled', 'inactive', 'void', 'past_due'].includes(normalized)) {
      return 'status--negative';
    }

    return 'status--neutral';
  }

  planChipClass(isHighlighted?: boolean): string {
    return isHighlighted ? 'plan-card plan-card--featured' : 'plan-card';
  }

  trackById(_: number, item: { id: number | string }): number | string {
    return item.id;
  }

  trackByBalanceCurrency(_: number, item: BillingWalletBalance): string {
    return item.currency.code;
  }

  trackByUuid(_: number, item: { uuid: string }): string {
    return item.uuid;
  }

  trackByFeatureKey(_: number, item: { featureKey: string }): string {
    return item.featureKey;
  }

  private async loadWallet(): Promise<void> {
    this.walletLoading = true;
    this.walletError = null;

    try {
      this.wallet = await firstValueFrom(this.billingService.loadWallet());
    } catch (error) {
      this.wallet = null;
      this.walletError = BillingService.extractError(error);
    } finally {
      this.walletLoading = false;
    }
  }

  private async loadWalletTransactions(): Promise<void> {
    this.walletTransactionsLoading = true;
    this.walletTransactionsError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadWalletTransactions());
      this.walletTransactions = response.items;
      this.walletTransactionsMeta = this.normalizeMeta(response.meta);
    } catch (error) {
      this.walletTransactions = [];
      this.walletTransactionsMeta = null;
      this.walletTransactionsError = BillingService.extractError(error);
    } finally {
      this.walletTransactionsLoading = false;
    }
  }

  private async loadInvoices(): Promise<void> {
    this.invoicesLoading = true;
    this.invoicesError = null;

    try {
      const response = await firstValueFrom(this.billingService.loadInvoices());
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

  private async loadPaymentMethods(): Promise<void> {
    this.paymentMethodsLoading = true;
    this.paymentMethodsError = null;

    try {
      this.paymentMethods = await firstValueFrom(this.billingService.loadPaymentMethods());
      this.syncPreferenceForm();
    } catch (error) {
      this.paymentMethods = [];
      this.paymentMethodsError = BillingService.extractError(error);
    } finally {
      this.paymentMethodsLoading = false;
    }
  }

  private async loadPaymentPreferences(): Promise<void> {
    this.paymentPreferencesLoading = true;
    this.paymentPreferencesError = null;

    try {
      this.paymentPreferences = await firstValueFrom(this.billingService.loadPaymentPreferences());
      this.syncPreferenceForm();
    } catch (error) {
      this.paymentPreferences = null;
      this.paymentPreferencesError = BillingService.extractError(error);
    } finally {
      this.paymentPreferencesLoading = false;
    }
  }

  private syncPreferenceForm(): void {
    if (!this.paymentPreferences) {
      return;
    }

    this.paymentPreferencesForm.patchValue({
      strategy: this.paymentPreferences.strategy,
      default_payment_method_id: String(this.paymentPreferences.default_payment_method?.id ?? ''),
      auto_charge_enabled: this.paymentPreferences.auto_charge_enabled,
      auto_top_up_enabled: this.paymentPreferences.auto_top_up_enabled,
      auto_top_up_threshold_amount: String(this.paymentPreferences.auto_top_up_threshold_amount ?? ''),
      auto_top_up_amount: String(this.paymentPreferences.auto_top_up_amount ?? ''),
      auto_top_up_currency: this.paymentPreferences.auto_top_up_currency?.code ?? 'USD',
      max_auto_top_up_per_day: String(this.paymentPreferences.max_auto_top_up_per_day ?? ''),
      max_auto_top_up_per_month: String(this.paymentPreferences.max_auto_top_up_per_month ?? ''),
    });
  }

  private normalizeMeta(meta: unknown): PaginatedMeta | null {
    if (!meta || typeof meta !== 'object') {
      return null;
    }

    const source = meta as Partial<PaginatedMeta>;

    return {
      current_page: Number(source.current_page ?? 1),
      last_page: Number(source.last_page ?? 1),
      per_page: Number(source.per_page ?? 10),
      total: Number(source.total ?? 0),
    };
  }

  private parseOptionalInteger(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
      return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
}
