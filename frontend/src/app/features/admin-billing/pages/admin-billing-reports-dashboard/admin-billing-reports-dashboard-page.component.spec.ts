import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { AdminBillingModule } from '../../admin-billing.module';
import { BillingService } from '../../../billing/services/billing.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { AdminBillingReportsDashboardPageComponent } from './admin-billing-reports-dashboard-page.component';

describe('AdminBillingReportsDashboardPageComponent', () => {
  function toDateInputValue(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function defaultDateFrom(): string {
    const date = new Date();
    date.setDate(date.getDate() - 29);
    return toDateInputValue(date);
  }

  function defaultDateTo(): string {
    return toDateInputValue(new Date());
  }

  const revenueSummary = {
    scope: 'revenue-summary',
    generated_at: '2026-06-13T08:00:00Z',
    filters: {
      date_from: '2026-05-15',
      date_to: '2026-06-13',
    },
    summary: {
      payment_count: 6,
      successful_payment_count: 5,
      revenue_amount: 784900,
      average_successful_payment_amount: 156980,
    },
    currency_breakdown: [
      {
        currency: 'USD',
        payment_count: 5,
        revenue_amount: 684900,
      },
    ],
    status_breakdown: [],
  };

  const paymentStatusSummary = {
    scope: 'payment-status-summary',
    generated_at: '2026-06-13T08:00:00Z',
    filters: {},
    summary: {
      payment_count: 6,
    },
    status_breakdown: [
      { status: 'succeeded', payment_count: 5, amount_total: 684900 },
      { status: 'failed', payment_count: 1, amount_total: 100000 },
    ],
  };

  const revenueByPlan = {
    scope: 'revenue-by-plan',
    generated_at: '2026-06-13T08:00:00Z',
    filters: {},
    rows: [
      { plan_id: 1, plan_slug: 'starter', plan_name: 'Starter', currency: 'USD', payment_count: 3, revenue_amount: 284900 },
      { plan_id: 2, plan_slug: 'pro', plan_name: 'Pro', currency: 'USD', payment_count: 2, revenue_amount: 500000 },
    ],
  };

  const revenueByCurrency = {
    scope: 'revenue-by-currency',
    generated_at: '2026-06-13T08:00:00Z',
    filters: {},
    rows: [
      { currency: 'USD', payment_count: 6, revenue_amount: 784900 },
    ],
  };

  const revenueBySellerCompany = {
    scope: 'revenue-by-seller-company',
    generated_at: '2026-06-13T08:00:00Z',
    filters: {},
    rows: [
      {
        company_id: 15,
        company_name: 'Acme Labs',
        seller_id: 42,
        seller_name: 'Acme Sales',
        currency: 'USD',
        payment_count: 4,
        revenue_amount: 584900,
      },
    ],
  };

  const subscriptionMetrics = {
    scope: 'subscription-metrics',
    generated_at: '2026-06-13T08:00:00Z',
    filters: {},
    summary: {
      subscription_count: 11,
      active_subscription_count: 8,
      trialing_subscription_count: 1,
      past_due_subscription_count: 1,
      cancelled_subscription_count: 1,
      new_subscription_count: 2,
    },
    status_breakdown: [
      { status: 'active', subscription_count: 8 },
      { status: 'past_due', subscription_count: 1 },
      { status: 'cancelled', subscription_count: 1 },
    ],
    plan_breakdown: [
      { plan_id: 1, plan_slug: 'starter', plan_name: 'Starter', subscription_count: 4 },
      { plan_id: 2, plan_slug: 'pro', plan_name: 'Pro', subscription_count: 7 },
    ],
  };

  const invoiceMetrics = {
    scope: 'invoice-metrics',
    generated_at: '2026-06-13T08:00:00Z',
    filters: {},
    summary: {
      invoice_count: 9,
      issued_invoice_count: 2,
      paid_invoice_count: 5,
      void_invoice_count: 1,
      overdue_invoice_count: 1,
    },
    status_breakdown: [
      { status: 'paid', invoice_count: 5, total_amount: 384900, paid_amount: 384900, due_amount: 0 },
      { status: 'overdue', invoice_count: 1, total_amount: 120000, paid_amount: 0, due_amount: 120000 },
    ],
    currency_breakdown: [
      { currency: 'USD', invoice_count: 9, total_amount: 504900, paid_amount: 384900, due_amount: 120000 },
    ],
  };

  const walletMetrics = {
    scope: 'wallet-metrics',
    generated_at: '2026-06-13T08:00:00Z',
    filters: {},
    summary: {
      wallet_count: 4,
      active_wallet_count: 3,
      suspended_wallet_count: 1,
      closed_wallet_count: 0,
      transaction_count: 14,
    },
    wallet_status_breakdown: [
      { status: 'active', wallet_count: 3 },
      { status: 'suspended', wallet_count: 1 },
    ],
    currency_breakdown: [
      { currency: 'USD', balance_count: 4, available_amount: 264900, held_amount: 12000 },
    ],
    transaction_breakdown: [
      { type: 'top_up', direction: 'credit', status: 'completed', currency: 'USD', transaction_count: 5, credited_amount: 404900, debited_amount: 0 },
      { type: 'adjustment', direction: 'debit', status: 'completed', currency: 'USD', transaction_count: 2, credited_amount: 0, debited_amount: 50000 },
    ],
  };

  function createBillingServiceMock(options: { operationalError?: boolean } = {}) {
    return {
      loadBillingRevenueSummary: vi.fn().mockReturnValue(of(revenueSummary)),
      loadBillingPaymentStatusSummary: options.operationalError
        ? vi.fn().mockReturnValue(throwError(() => ({
          status: 403,
          code: 'forbidden',
          message: 'Denied',
          errors: { permission: ['billing.reports.view'] },
        })))
        : vi.fn().mockReturnValue(of(paymentStatusSummary)),
      loadBillingRevenueByPlan: vi.fn().mockReturnValue(of(revenueByPlan)),
      loadBillingRevenueByCurrency: vi.fn().mockReturnValue(of(revenueByCurrency)),
      loadBillingRevenueBySellerCompany: vi.fn().mockReturnValue(of(revenueBySellerCompany)),
      loadBillingSubscriptionMetrics: vi.fn().mockReturnValue(of(subscriptionMetrics)),
      loadBillingInvoiceMetrics: vi.fn().mockReturnValue(of(invoiceMetrics)),
      loadBillingWalletMetrics: vi.fn().mockReturnValue(of(walletMetrics)),
    };
  }

  function createPermissionServiceMock(canViewFinancialReports = true) {
    return {
      hasRole: vi.fn().mockReturnValue(false),
      hasPermission: vi.fn().mockImplementation((permission: string) => {
        if (permission === 'billing.reports.view') {
          return true;
        }

        if (permission === 'billing.reports.view_financials') {
          return canViewFinancialReports;
        }

        return false;
      }),
      hasAnyPermission: vi.fn().mockImplementation((permissions: string[]) => permissions.includes('billing.reports.view')),
    };
  }

  async function createFixture(options: { operationalError?: boolean; canViewFinancialReports?: boolean } = {}) {
    const billingServiceMock = createBillingServiceMock({ operationalError: options.operationalError });
    const permissionServiceMock = createPermissionServiceMock(options.canViewFinancialReports ?? true);

    TestBed.resetTestingModule();
    await TestBed.configureTestingModule({
      imports: [AdminBillingModule, RouterTestingModule],
      providers: [
        { provide: BillingService, useValue: billingServiceMock },
        { provide: PermissionService, useValue: permissionServiceMock },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(AdminBillingReportsDashboardPageComponent);
    fixture.detectChanges();
    await fixture.whenStable();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await fixture.whenStable();

    return { fixture, component: fixture.componentInstance, billingServiceMock, permissionServiceMock };
  }

  it('renders the reports dashboard and loads backend aggregates', async () => {
    const { component, billingServiceMock } = await createFixture();

    expect(billingServiceMock.loadBillingRevenueSummary).toHaveBeenCalledTimes(1);
    expect(billingServiceMock.loadBillingRevenueSummary).toHaveBeenCalledWith(expect.objectContaining({
      date_from: defaultDateFrom(),
      date_to: defaultDateTo(),
    }));
    expect(billingServiceMock.loadBillingPaymentStatusSummary).toHaveBeenCalledTimes(1);
    expect(billingServiceMock.loadBillingRevenueByPlan).toHaveBeenCalledTimes(1);
    expect(billingServiceMock.loadBillingWalletMetrics).toHaveBeenCalledTimes(1);
    expect(component.revenueSummary?.summary.successful_payment_count).toBe(5);
    expect(component.paymentStatusSummary?.summary.payment_count).toBe(6);
    expect(component.revenueSummaryCurrencyLabel()).toContain('6,849.00 USD');
  });

  it('locks financial totals when the financial permission is missing', async () => {
    const { component, billingServiceMock } = await createFixture({ canViewFinancialReports: false });

    expect(billingServiceMock.loadBillingRevenueSummary).not.toHaveBeenCalled();
    expect(billingServiceMock.loadBillingPaymentStatusSummary).toHaveBeenCalledTimes(1);
    expect(component.canViewFinancialReports).toBe(false);
    expect(component.revenueSummary).toBeNull();
    expect(component.summaryCards[0].value).toBe('Locked');
  });

  it('surfaces backend errors from report calls', async () => {
    const { component } = await createFixture({ operationalError: true });

    expect(component.reportsError?.code).toBe('forbidden');
    expect(component.reportsError?.message).toBe('Denied');
    expect(component.paymentStatusSummaryError?.code).toBe('forbidden');
  });
});
