import { Component, OnInit, inject } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { firstValueFrom, type Observable } from 'rxjs';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { BillingService } from '../../../billing/services/billing.service';
import type {
  BillingInvoiceMetricsReport,
  BillingPaymentStatusSummaryReport,
  BillingPortalError,
  BillingReportFilters,
  BillingRevenueByCurrencyReport,
  BillingRevenueByPlanReport,
  BillingRevenueBySellerCompanyReport,
  BillingRevenueSummaryReport,
  BillingSubscriptionMetricsReport,
  BillingWalletMetricsReport,
} from '../../../billing/models/billing.model';

type ReportMetricCard = {
  label: string;
  value: string;
  hint: string;
  tone: 'positive' | 'pending' | 'negative' | 'neutral';
};

@Component({
  selector: 'app-admin-billing-reports-dashboard-page',
  templateUrl: './admin-billing-reports-dashboard-page.component.html',
  styleUrls: ['./admin-billing-reports-dashboard-page.component.scss'],
  standalone: false,
})
export class AdminBillingReportsDashboardPageComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly permissionService = inject(PermissionService);

  readonly reportFiltersForm = this.fb.group({
    date_from: [this.defaultDateFrom(), [Validators.maxLength(10)]],
    date_to: [this.defaultDateTo(), [Validators.maxLength(10)]],
    currency: [''],
    company_id: [''],
    seller_id: [''],
    user_id: [''],
    plan_id: [''],
    payment_status: [''],
    invoice_status: [''],
    subscription_status: [''],
    wallet_status: [''],
  });

  reportsLoading = false;
  reportsError: BillingPortalError | null = null;

  revenueSummaryLoading = false;
  revenueSummaryError: BillingPortalError | null = null;
  revenueSummary: BillingRevenueSummaryReport | null = null;

  paymentStatusSummaryLoading = false;
  paymentStatusSummaryError: BillingPortalError | null = null;
  paymentStatusSummary: BillingPaymentStatusSummaryReport | null = null;

  revenueByPlanLoading = false;
  revenueByPlanError: BillingPortalError | null = null;
  revenueByPlan: BillingRevenueByPlanReport | null = null;

  revenueByCurrencyLoading = false;
  revenueByCurrencyError: BillingPortalError | null = null;
  revenueByCurrency: BillingRevenueByCurrencyReport | null = null;

  revenueBySellerCompanyLoading = false;
  revenueBySellerCompanyError: BillingPortalError | null = null;
  revenueBySellerCompany: BillingRevenueBySellerCompanyReport | null = null;

  subscriptionMetricsLoading = false;
  subscriptionMetricsError: BillingPortalError | null = null;
  subscriptionMetrics: BillingSubscriptionMetricsReport | null = null;

  invoiceMetricsLoading = false;
  invoiceMetricsError: BillingPortalError | null = null;
  invoiceMetrics: BillingInvoiceMetricsReport | null = null;

  walletMetricsLoading = false;
  walletMetricsError: BillingPortalError | null = null;
  walletMetrics: BillingWalletMetricsReport | null = null;

  constructor(private readonly billingService: BillingService) {}

  ngOnInit(): void {
    void this.refreshReports();
  }

  get isAdmin(): boolean {
    return this.permissionService.hasRole('admin');
  }

  get canViewReports(): boolean {
    return this.isAdmin || this.permissionService.hasPermission('billing.reports.view');
  }

  get canViewFinancialReports(): boolean {
    return this.isAdmin || this.permissionService.hasPermission('billing.reports.view_financials');
  }

  get summaryCards(): ReportMetricCard[] {
    const paymentBreakdown = this.paymentStatusSummary?.status_breakdown ?? [];
    const subscriptionBreakdown = this.subscriptionMetrics?.status_breakdown ?? [];
    const invoiceBreakdown = this.invoiceMetrics?.status_breakdown ?? [];
    const walletBreakdown = this.walletMetrics?.transaction_breakdown ?? [];

    return [
      {
        label: 'Successful revenue',
        value: this.canViewFinancialReports ? this.revenueSummaryCurrencyLabel() : 'Locked',
        hint: this.canViewFinancialReports
          ? 'Authoritative backend aggregate by currency'
          : 'Grant billing.reports.view_financials to unlock money totals',
        tone: this.canViewFinancialReports ? 'positive' : 'neutral',
      },
      {
        label: 'Successful payments',
        value: this.countFromPaymentStatus(paymentBreakdown, 'succeeded').toString(),
        hint: 'Backend payment status summary',
        tone: 'positive',
      },
      {
        label: 'Failed payments',
        value: this.countFromPaymentStatus(paymentBreakdown, 'failed').toString(),
        hint: 'Backend payment status summary',
        tone: 'negative',
      },
      {
        label: 'Pending payments',
        value: this.countFromPaymentStatus(paymentBreakdown, 'pending').toString(),
        hint: 'Backend payment status summary',
        tone: 'pending',
      },
      {
        label: 'Active subscriptions',
        value: this.countFromSubscriptionStatus(subscriptionBreakdown, 'active').toString(),
        hint: 'Lifecycle count from backend aggregates',
        tone: 'positive',
      },
      {
        label: 'Past due subscriptions',
        value: this.countFromSubscriptionStatus(subscriptionBreakdown, 'past_due').toString(),
        hint: 'Lifecycle count from backend aggregates',
        tone: 'pending',
      },
      {
        label: 'Paid invoices',
        value: this.countFromInvoiceStatus(invoiceBreakdown, 'paid').toString(),
        hint: 'Invoice metric summary',
        tone: 'positive',
      },
      {
        label: 'Unpaid / pending invoices',
        value: this.countOutstandingInvoices(invoiceBreakdown).toString(),
        hint: 'Issued, failed, overdue, and payment-pending invoices',
        tone: 'pending',
      },
      {
        label: 'Wallet top-ups',
        value: this.walletAmountSummary(walletBreakdown, 'top_up', 'credited_amount'),
        hint: 'Wallet transaction aggregate',
        tone: 'positive',
      },
      {
        label: 'Wallet debits',
        value: this.walletAmountSummary(walletBreakdown, 'debit', 'debited_amount'),
        hint: 'Wallet transaction aggregate',
        tone: 'negative',
      },
    ];
  }

  get notes(): Array<{ title: string; body: string }> {
    return [
      {
        title: 'MRR / ARR',
        body: 'MRR/ARR stays unavailable until subscription interval pricing is authoritative in the backend.',
      },
      {
        title: 'CSV export',
        body: 'CSV export requires a backend export endpoint, so the button remains disabled for now.',
      },
      {
        title: 'Source of truth',
        body: 'All money totals come from backend report aggregates, not paginated payment lists.',
      },
    ];
  }

  async refreshReports(): Promise<void> {
    if (!this.reportFiltersForm.valid) {
      this.reportFiltersForm.markAllAsTouched();
      this.reportsError = {
        status: 422,
        code: 'validation',
        message: 'Please fix the report filters before loading data.',
        errors: null,
      };
      return;
    }

    await Promise.resolve();

    this.reportsLoading = true;
    this.reportsError = null;

    await Promise.all([
      this.loadOperationalReports(),
      this.canViewFinancialReports ? this.loadFinancialReports() : this.resetFinancialReports(),
    ]);

    this.reportsLoading = false;
  }

  async resetFilters(): Promise<void> {
    this.reportFiltersForm.reset({
      date_from: this.defaultDateFrom(),
      date_to: this.defaultDateTo(),
      currency: '',
      company_id: '',
      seller_id: '',
      user_id: '',
      plan_id: '',
      payment_status: '',
      invoice_status: '',
      subscription_status: '',
      wallet_status: '',
    });

    await this.refreshReports();
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

  formatAmount(amount: number | null | undefined, currencyCode = 'USD', precision = 2): string {
    if (amount === null || amount === undefined) {
      return '-';
    }

    const normalized = amount / Math.pow(10, precision || 2);

    try {
      return `${new Intl.NumberFormat('en-US', {
        minimumFractionDigits: precision || 2,
        maximumFractionDigits: precision || 2,
      }).format(normalized)} ${currencyCode}`;
    } catch {
      return `${normalized.toFixed(precision || 2)} ${currencyCode}`;
    }
  }

  errorFieldLines(error: BillingPortalError | null): string[] {
    return BillingService.describeErrorFields(error?.errors ?? null);
  }

  trackByRevenuePlan(_: number, row: BillingRevenueByPlanReport['rows'][number]): string {
    return `${row.plan_id ?? 'unassigned'}:${row.currency ?? 'any'}`;
  }

  trackByRevenueCurrency(_: number, row: BillingRevenueByCurrencyReport['rows'][number]): string {
    return `${row.currency ?? 'unknown'}`;
  }

  trackByRevenueSellerCompany(_: number, row: BillingRevenueBySellerCompanyReport['rows'][number]): string {
    return `${row.company_id ?? 'company'}:${row.seller_id ?? 'seller'}:${row.currency ?? 'any'}`;
  }

  trackByPaymentStatus(_: number, row: BillingPaymentStatusSummaryReport['status_breakdown'][number]): string {
    return String(row.status ?? 'unknown');
  }

  trackBySubscriptionStatus(_: number, row: BillingSubscriptionMetricsReport['status_breakdown'][number]): string {
    return String(row.status ?? 'unknown');
  }

  trackBySubscriptionPlan(_: number, row: BillingSubscriptionMetricsReport['plan_breakdown'][number]): string {
    return `${row.plan_id ?? 'unassigned'}`;
  }

  trackByInvoiceStatus(_: number, row: BillingInvoiceMetricsReport['status_breakdown'][number]): string {
    return String(row.status ?? 'unknown');
  }

  trackByInvoiceCurrency(_: number, row: BillingInvoiceMetricsReport['currency_breakdown'][number]): string {
    return String(row.currency ?? 'unknown');
  }

  trackByWalletStatus(_: number, row: BillingWalletMetricsReport['wallet_status_breakdown'][number]): string {
    return String(row.status ?? 'unknown');
  }

  trackByWalletCurrency(_: number, row: BillingWalletMetricsReport['currency_breakdown'][number]): string {
    return String(row.currency ?? 'unknown');
  }

  trackByWalletTransaction(_: number, row: BillingWalletMetricsReport['transaction_breakdown'][number]): string {
    return `${row.type ?? 'unknown'}:${row.direction ?? 'neutral'}:${row.currency ?? 'any'}`;
  }

  private async loadFinancialReports(): Promise<void> {
    this.revenueSummaryLoading = true;
    this.revenueSummaryError = null;
    this.revenueByPlanLoading = true;
    this.revenueByPlanError = null;
    this.revenueByCurrencyLoading = true;
    this.revenueByCurrencyError = null;
    this.revenueBySellerCompanyLoading = true;
    this.revenueBySellerCompanyError = null;

    const filters = this.reportFilters();

    await Promise.all([
      this.runFinancialCall('revenueSummary', () => this.billingService.loadBillingRevenueSummary(filters)),
      this.runFinancialCall('revenueByPlan', () => this.billingService.loadBillingRevenueByPlan(filters)),
      this.runFinancialCall('revenueByCurrency', () => this.billingService.loadBillingRevenueByCurrency(filters)),
      this.runFinancialCall('revenueBySellerCompany', () => this.billingService.loadBillingRevenueBySellerCompany(filters)),
    ]);

    this.reportsError ??= this.revenueSummaryError ?? this.revenueByPlanError ?? this.revenueByCurrencyError ?? this.revenueBySellerCompanyError;
  }

  private async loadOperationalReports(): Promise<void> {
    this.paymentStatusSummaryLoading = true;
    this.paymentStatusSummaryError = null;
    this.subscriptionMetricsLoading = true;
    this.subscriptionMetricsError = null;
    this.invoiceMetricsLoading = true;
    this.invoiceMetricsError = null;
    this.walletMetricsLoading = true;
    this.walletMetricsError = null;

    const filters = this.reportFilters();

    await Promise.all([
      this.runOperationalCall('paymentStatusSummary', () => this.billingService.loadBillingPaymentStatusSummary(filters)),
      this.runOperationalCall('subscriptionMetrics', () => this.billingService.loadBillingSubscriptionMetrics(filters)),
      this.runOperationalCall('invoiceMetrics', () => this.billingService.loadBillingInvoiceMetrics(filters)),
      this.runOperationalCall('walletMetrics', () => this.billingService.loadBillingWalletMetrics(filters)),
    ]);

    this.reportsError ??= this.paymentStatusSummaryError ?? this.subscriptionMetricsError ?? this.invoiceMetricsError ?? this.walletMetricsError;
  }

  private async runFinancialCall(
    key: 'revenueSummary' | 'revenueByPlan' | 'revenueByCurrency' | 'revenueBySellerCompany',
    loader: () => Observable<unknown>,
  ): Promise<void> {
    try {
      const data = await firstValueFrom(loader());
      (this as Record<string, unknown>)[key] = data;
    } catch (error) {
      const typed = BillingService.extractError(error);
      if (key === 'revenueSummary') {
        this.revenueSummary = null;
        this.revenueSummaryError = typed;
      }
      if (key === 'revenueByPlan') {
        this.revenueByPlan = null;
        this.revenueByPlanError = typed;
      }
      if (key === 'revenueByCurrency') {
        this.revenueByCurrency = null;
        this.revenueByCurrencyError = typed;
      }
      if (key === 'revenueBySellerCompany') {
        this.revenueBySellerCompany = null;
        this.revenueBySellerCompanyError = typed;
      }
    } finally {
      if (key === 'revenueSummary') this.revenueSummaryLoading = false;
      if (key === 'revenueByPlan') this.revenueByPlanLoading = false;
      if (key === 'revenueByCurrency') this.revenueByCurrencyLoading = false;
      if (key === 'revenueBySellerCompany') this.revenueBySellerCompanyLoading = false;
    }
  }

  private async runOperationalCall(
    key: 'paymentStatusSummary' | 'subscriptionMetrics' | 'invoiceMetrics' | 'walletMetrics',
    loader: () => Observable<unknown>,
  ): Promise<void> {
    try {
      const data = await firstValueFrom(loader());
      (this as Record<string, unknown>)[key] = data;
    } catch (error) {
      const typed = BillingService.extractError(error);
      if (key === 'paymentStatusSummary') {
        this.paymentStatusSummary = null;
        this.paymentStatusSummaryError = typed;
      }
      if (key === 'subscriptionMetrics') {
        this.subscriptionMetrics = null;
        this.subscriptionMetricsError = typed;
      }
      if (key === 'invoiceMetrics') {
        this.invoiceMetrics = null;
        this.invoiceMetricsError = typed;
      }
      if (key === 'walletMetrics') {
        this.walletMetrics = null;
        this.walletMetricsError = typed;
      }
    } finally {
      if (key === 'paymentStatusSummary') this.paymentStatusSummaryLoading = false;
      if (key === 'subscriptionMetrics') this.subscriptionMetricsLoading = false;
      if (key === 'invoiceMetrics') this.invoiceMetricsLoading = false;
      if (key === 'walletMetrics') this.walletMetricsLoading = false;
    }
  }

  private async resetFinancialReports(): Promise<void> {
    this.revenueSummary = null;
    this.revenueByPlan = null;
    this.revenueByCurrency = null;
    this.revenueBySellerCompany = null;
    this.revenueSummaryLoading = false;
    this.revenueByPlanLoading = false;
    this.revenueByCurrencyLoading = false;
    this.revenueBySellerCompanyLoading = false;
    this.revenueSummaryError = null;
    this.revenueByPlanError = null;
    this.revenueByCurrencyError = null;
    this.revenueBySellerCompanyError = null;
  }

  private reportFilters(): BillingReportFilters {
    const raw = this.reportFiltersForm.getRawValue();

    return {
      date_from: this.normalizeText(raw.date_from),
      date_to: this.normalizeText(raw.date_to),
      currency: this.normalizeText(raw.currency)?.toUpperCase() ?? null,
      company_id: this.toOptionalInteger(raw.company_id),
      seller_id: this.toOptionalInteger(raw.seller_id),
      user_id: this.toOptionalInteger(raw.user_id),
      plan_id: this.toOptionalInteger(raw.plan_id),
      payment_status: this.normalizeText(raw.payment_status),
      invoice_status: this.normalizeText(raw.invoice_status),
      subscription_status: this.normalizeText(raw.subscription_status),
      wallet_status: this.normalizeText(raw.wallet_status),
    };
  }

  revenueSummaryCurrencyLabel(): string {
    const breakdown = this.revenueSummary?.currency_breakdown ?? [];

    if (breakdown.length === 0) {
      return '0.00';
    }

    return breakdown
      .map((row) => `${this.formatAmount(row.revenue_amount, row.currency ?? 'USD')} (${row.currency ?? 'unknown'})`)
      .join(' | ');
  }

  private countFromPaymentStatus(rows: BillingPaymentStatusSummaryReport['status_breakdown'], status: string): number {
    const row = rows.find((item) => String(item.status ?? '').toLowerCase() === status.toLowerCase());
    return Number(row?.payment_count ?? 0);
  }

  private countFromSubscriptionStatus(rows: BillingSubscriptionMetricsReport['status_breakdown'], status: string): number {
    const row = rows.find((item) => String(item.status ?? '').toLowerCase() === status.toLowerCase());
    return Number(row?.subscription_count ?? 0);
  }

  private countFromInvoiceStatus(rows: BillingInvoiceMetricsReport['status_breakdown'], status: string): number {
    const row = rows.find((item) => String(item.status ?? '').toLowerCase() === status.toLowerCase());
    return Number(row?.invoice_count ?? 0);
  }

  private countOutstandingInvoices(rows: BillingInvoiceMetricsReport['status_breakdown']): number {
    return rows
      .filter((item) => ['issued', 'payment_pending', 'failed', 'overdue'].includes(String(item.status ?? '').toLowerCase()))
      .reduce((total, row) => total + Number(row.invoice_count ?? 0), 0);
  }

  private walletAmountSummary(
    rows: BillingWalletMetricsReport['transaction_breakdown'],
    type: string,
    key: 'credited_amount' | 'debited_amount',
  ): string {
    const row = rows.find((item) => String(item.type ?? '').toLowerCase() === type.toLowerCase());
    const amount = Number(row?.[key] ?? 0);
    return this.formatAmount(amount, row?.currency ?? 'USD');
  }

  private toOptionalInteger(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
      return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  private normalizeText(value: string | number | null | undefined): string | null {
    if (value === null || value === undefined) {
      return null;
    }

    const normalized = String(value).trim();
    return normalized === '' ? null : normalized;
  }

  private defaultDateFrom(): string {
    const date = new Date();
    date.setDate(date.getDate() - 29);
    return this.toDateInputValue(date);
  }

  private defaultDateTo(): string {
    return this.toDateInputValue(new Date());
  }

  private toDateInputValue(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }
}
