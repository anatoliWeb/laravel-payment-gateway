import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { Observable, of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { AdminBillingModule } from '../../admin-billing.module';
import { BillingIdempotencyService } from '../../../billing/services/billing-idempotency.service';
import { BillingService } from '../../../billing/services/billing.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { AdminBillingDashboardPageComponent } from './admin-billing-dashboard-page.component';

describe('AdminBillingDashboardPageComponent', () => {
  let fixture: ComponentFixture<AdminBillingDashboardPageComponent>;
  let component: AdminBillingDashboardPageComponent;

  const asyncValue = <T>(value: T) => new Observable<T>((subscriber) => {
    Promise.resolve().then(() => {
      subscriber.next(value);
      subscriber.complete();
    });
  });

  const invoice = {
    id: 88,
    uuid: 'invoice-88',
    number: 'INV-2026-0088',
    status: 'issued',
    currency: 'USD',
    subtotal_amount: 10000,
    discount_amount: 0,
    tax_amount: 0,
    total_amount: 10000,
    paid_amount: 0,
    due_amount: 10000,
    payer_user_id: 15,
    company_id: 3,
    seller_id: 7,
    subscription_id: 12,
    payment_id: null,
    description: 'Operator invoice',
    issued_at: '2026-06-12T08:00:00Z',
    due_at: '2026-07-01T00:00:00Z',
    paid_at: null,
    voided_at: null,
    overdue_at: null,
    items: [
      {
        item_type: 'service',
        description: 'Implementation work',
        quantity: 1,
        unit_amount: 10000,
        discount_amount: 0,
        tax_amount: 0,
        metadata: {},
      },
    ],
    created_at: '2026-06-12T08:00:00Z',
  };

  const payment = {
    id: 77,
    uuid: 'payment-77',
    user_id: 15,
    payer_user_id: 15,
    company_id: 3,
    seller_id: 7,
    subscription_id: 12,
    invoice_id: 88,
    parent_payment_id: null,
    provider_account_id: 5,
    amount: 10000,
    currency: 'USD',
    status: 'succeeded',
    payment_source: 'payment_method',
    payment_method_summary: { id: 101, uuid: 'pm-1', type: 'fake_card' },
    provider: 'simulator',
    provider_reference: 'prov-77',
    description: 'Operator payment',
    failure_reason: null,
    callback_url: 'https://example.test/callback',
    metadata: {},
    ownership_metadata: {},
    paid_at: '2026-06-12T08:10:00Z',
    failed_at: null,
    expired_at: null,
    cancelled_at: null,
    transactions_count: 1,
    webhook_deliveries_count: 1,
    created_at: '2026-06-12T08:10:00Z',
    updated_at: '2026-06-12T08:10:00Z',
  };

  const paymentTransaction = {
    id: 701,
    payment_id: 77,
    type: 'payment_succeeded',
    status_from: 'processing',
    status_to: 'succeeded',
    amount: 10000,
    currency: 'USD',
    message: 'Payment success simulated.',
    payload: { source: 'payment_simulation_service' },
    created_at: '2026-06-12T08:11:00Z',
  };

  const activity = {
    id: 501,
    user_id: 1,
    user: { id: 1, name: 'Admin', email: 'admin@test.com' },
    action: 'billing.payment_created',
    description: 'Billing payment created',
    meta: { source: 'admin_billing_ui' },
    created_at: '2026-06-12T08:30:00Z',
  };

  const webhook = {
    id: 9001,
    uuid: 'webhook-9001',
    payment_id: 77,
    event_type: 'payment.updated',
    status: 'failed',
    attempts: 2,
    max_attempts: 5,
    next_attempt_at: '2026-06-12T09:00:00Z',
    delivered_at: null,
    failed_at: '2026-06-12T08:45:00Z',
    last_error: 'Gateway timeout',
    status_code: 500,
    callback_host: 'example.test',
    created_at: '2026-06-12T08:40:00Z',
  };

  const subscription = {
    id: 12,
    uuid: 'subscription-12',
    user_id: 15,
    plan_id: 4,
    plan_slug: 'pro',
    status: 'active',
    started_at: '2026-06-01T00:00:00Z',
    current_period_start: '2026-06-01T00:00:00Z',
    current_period_end: '2026-07-01T00:00:00Z',
    trial_ends_at: null,
    cancelled_at: null,
    cancel_at_period_end: false,
    ended_at: null,
    metadata: {},
    created_at: '2026-06-01T00:00:00Z',
  };

  const walletTransaction = {
    uuid: 'wallet-tx-1',
    type: 'adjustment',
    direction: 'credit',
    amount: 5000,
    currency: 'USD',
    status: 'completed',
    reason: 'Support-approved correction',
    reference: 'ticket-77',
    payment_uuid: null,
    balance_available_before: 1000,
    balance_available_after: 6000,
    balance_held_before: 0,
    balance_held_after: 0,
    metadata: {},
    created_at: '2026-06-12T08:50:00Z',
  };

  const wallet = {
    id: 33,
    uuid: 'wallet-33',
    user_id: 15,
    status: 'active',
    balances: [
      {
        currency: { code: 'USD', name: 'US Dollar', symbol: '$', decimal_precision: 2 },
        available_amount: 25000,
        held_amount: 2000,
        updated_at: '2026-06-12T09:00:00Z',
      },
    ],
    metadata: {},
    created_at: '2026-06-10T08:00:00Z',
    updated_at: '2026-06-12T09:00:00Z',
  };

  const idempotencyKey = {
    id: 44,
    user_id: 15,
    scope: 'payment.create',
    method: 'POST',
    endpoint: '/api/v1/billing/payments',
    status: 'completed',
    key_fingerprint: 'sha256:demo12345678',
    request_hash: 'request-hash',
    response_status: 201,
    related_type: 'App\\Models\\Payment',
    related_id: 77,
    locked_until: null,
    expires_at: '2026-06-13T00:00:00Z',
    created_at: '2026-06-12T08:00:00Z',
    updated_at: '2026-06-12T08:00:00Z',
  };

  const providerAccount = {
    id: 55,
    uuid: 'provider-account-55',
    user_id: 15,
    company_id: 3,
    seller_id: 7,
    provider: 'simulator',
    display_name: 'Demo Platform Simulator',
    status: 'active',
    mode: 'test',
    config_source: 'database',
    public_config: { seeded: true },
    capabilities: { charge: true, refund: true },
    masked_credentials: { api_key: '***0000' },
    last_verified_at: '2026-06-12T07:45:00Z',
    metadata: {},
    created_at: '2026-06-10T08:00:00Z',
    updated_at: '2026-06-12T08:00:00Z',
  };

  const restriction = {
    id: 66,
    user_id: 15,
    type: 'billing_blocked',
    scope: 'billing',
    feature_key: null,
    reason: 'Manual billing review demo',
    is_active: true,
    starts_at: '2026-06-11T08:00:00Z',
    ends_at: null,
    created_by: 1,
    metadata: {},
    created_at: '2026-06-11T08:00:00Z',
    updated_at: '2026-06-12T08:00:00Z',
  };

  const featureOverride = {
    id: 77,
    user_id: 15,
    subscription_id: 12,
    feature_key: 'chat.messages.daily',
    value: '5000',
    value_type: 'integer',
    period: 'daily',
    reset_policy: 'calendar_day',
    is_enabled: true,
    priority: 100,
    reason: 'Demo chat limit uplift',
    starts_at: '2026-06-11T08:00:00Z',
    ends_at: null,
    created_by: 1,
    metadata: {},
    created_at: '2026-06-11T08:00:00Z',
    updated_at: '2026-06-12T08:00:00Z',
  };

  const billingServiceMock = {
    loadAdminPayments: vi.fn().mockReturnValue(of({ items: [payment], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    loadAdminPayment: vi.fn().mockReturnValue(of(payment)),
    loadAdminPaymentTransactions: vi.fn().mockReturnValue(of({ items: [paymentTransaction], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    loadInvoices: vi.fn().mockReturnValue(of({ items: [invoice], meta: { current_page: 1, last_page: 1, per_page: 6, total: 1 } })),
    loadInvoice: vi.fn().mockReturnValue(of(invoice)),
    loadActivityLogs: vi.fn().mockReturnValue(of({ items: [activity], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    loadSubscription: vi.fn().mockReturnValue(of(subscription)),
    loadWebhookDeliveries: vi.fn().mockReturnValue(of({ items: [webhook], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    retryWebhookDelivery: vi.fn().mockReturnValue(of(webhook)),
    adjustWallet: vi.fn().mockReturnValue(of(walletTransaction)),
    loadAdminWallets: vi.fn().mockReturnValue(of({ items: [wallet], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    loadAdminWallet: vi.fn().mockReturnValue(of(wallet)),
    loadAdminWalletTransactions: vi.fn().mockReturnValue(of({ items: [walletTransaction], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    loadAdminIdempotencyKeys: vi.fn().mockReturnValue(of({ items: [idempotencyKey], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    loadAdminIdempotencyKey: vi.fn().mockReturnValue(of(idempotencyKey)),
    loadAdminProviderAccounts: vi.fn().mockReturnValue(of({ items: [providerAccount], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    loadAdminProviderAccount: vi.fn().mockReturnValue(of(providerAccount)),
    loadAdminRestrictions: vi.fn().mockReturnValue(of({ items: [restriction], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    loadAdminRestriction: vi.fn().mockReturnValue(of(restriction)),
    loadAdminFeatureOverrides: vi.fn().mockReturnValue(of({ items: [featureOverride], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    loadAdminFeatureOverride: vi.fn().mockReturnValue(of(featureOverride)),
  };

  const permissionServiceMock = {
    hasRole: vi.fn().mockReturnValue(true),
    hasPermission: vi.fn().mockReturnValue(true),
    hasAnyPermission: vi.fn().mockReturnValue(true),
  };

  beforeEach(async () => {
    vi.clearAllMocks();
    await TestBed.configureTestingModule({
      imports: [AdminBillingModule, RouterTestingModule],
      providers: [
        { provide: BillingService, useValue: billingServiceMock },
        { provide: BillingIdempotencyService, useValue: { createKey: vi.fn().mockReturnValue('admin-wallet-adjustment-key') } },
        { provide: PermissionService, useValue: permissionServiceMock },
      ],
    }).compileComponents();

    vi.spyOn(AdminBillingDashboardPageComponent.prototype, 'ngOnInit').mockImplementation(() => {});
    fixture = TestBed.createComponent(AdminBillingDashboardPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('starts the initial refresh immediately in ngOnInit without waiting for a second navigation', () => {
    const refreshSpy = vi.spyOn(component, 'refresh').mockResolvedValue();

    vi.mocked(AdminBillingDashboardPageComponent.prototype.ngOnInit).mockRestore();
    component.ngOnInit();

    expect(refreshSpy).toHaveBeenCalledTimes(1);
  });

  it('renders the admin billing dashboard and documented gaps', () => {
    expect(fixture.nativeElement.textContent).toContain('Billing Management');
    expect(fixture.nativeElement.textContent).toContain('UUID-backed admin payment view');
    expect(fixture.nativeElement.textContent).toContain('Activity logs');
    expect(fixture.nativeElement.textContent).toContain('Wallets');
    expect(fixture.nativeElement.textContent).toContain('Provider accounts');
  });

  it('renders payments, invoices, and wallets in the DOM after async paginated responses', async () => {
    billingServiceMock.loadAdminPayments.mockReturnValueOnce(asyncValue({
      items: [payment],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 },
    }));
    billingServiceMock.loadInvoices.mockReturnValueOnce(asyncValue({
      items: [invoice],
      meta: { current_page: 1, last_page: 1, per_page: 6, total: 1 },
    }));
    billingServiceMock.loadAdminWallets.mockReturnValueOnce(asyncValue({
      items: [wallet],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 },
    }));
    billingServiceMock.loadActivityLogs.mockReturnValueOnce(asyncValue({
      items: [activity],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 },
    }));
    billingServiceMock.loadAdminIdempotencyKeys.mockReturnValueOnce(asyncValue({
      items: [idempotencyKey],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 },
    }));
    billingServiceMock.loadAdminProviderAccounts.mockReturnValueOnce(asyncValue({
      items: [providerAccount],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 },
    }));
    billingServiceMock.loadAdminRestrictions.mockReturnValueOnce(asyncValue({
      items: [restriction],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 },
    }));
    billingServiceMock.loadAdminFeatureOverrides.mockReturnValueOnce(asyncValue({
      items: [featureOverride],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 },
    }));

    const localFixture = TestBed.createComponent(AdminBillingDashboardPageComponent);
    const localComponent = localFixture.componentInstance;
    await localComponent.refresh();
    localFixture.detectChanges();

    const text = localFixture.nativeElement.textContent;
    expect(text).toContain(payment.uuid);
    expect(text).toContain(invoice.number);
    expect(text).toContain(wallet.uuid);
    expect(text).not.toContain('Loading payments...');
    expect(text).not.toContain('Loading invoices...');
    expect(text).not.toContain('Loading wallets...');
    expect(localComponent.paymentsLoading).toBe(false);
    expect(localComponent.invoicesLoading).toBe(false);
    expect(localComponent.walletsLoading).toBe(false);
  });

  it('validates the wallet adjustment reason field', async () => {
    component.walletAdjustmentForm.patchValue({
      user_id: '42',
      currency: 'USD',
      amount: '5000',
      direction: 'credit',
      reason: '',
    });

    await component.submitWalletAdjustment();

    expect(component.walletAdjustmentError?.code).toBe('validation');
    expect(component.walletAdjustmentForm.controls.reason.errors).toBeTruthy();
  });

  it('retries a webhook delivery when permission is available', async () => {
    component.webhookLookupForm.patchValue({ payment_id: '77' });
    await component.lookupWebhookDeliveries();

    expect(component.webhookDeliveries.length).toBe(1);

    await component.retryWebhook(component.webhookDeliveries[0]);

    expect(billingServiceMock.retryWebhookDelivery).toHaveBeenCalledWith(9001);
    expect(component.webhookRetryMessage).toContain('queued for retry');
  });

  it('does not keep successful sections in loading state when one section fails', async () => {
    billingServiceMock.loadAdminPayments.mockReturnValueOnce(asyncValue({
      items: [payment],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 },
    }));
    billingServiceMock.loadInvoices.mockReturnValueOnce(throwError(() => ({
      status: 500,
      code: 'server',
      message: 'Invoices failed.',
    })));
    billingServiceMock.loadAdminWallets.mockReturnValueOnce(asyncValue({
      items: [wallet],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 },
    }));

    await component.refresh();
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(component.paymentsLoading).toBe(false);
    expect(component.invoicesLoading).toBe(false);
    expect(component.walletsLoading).toBe(false);
    expect(component.payments.length).toBe(1);
    expect(component.wallets.length).toBe(1);
    expect(component.invoicesError?.message).toBe('Invoices failed.');
    expect(text).toContain(payment.uuid);
    expect(text).toContain(wallet.uuid);
    expect(text).toContain('Invoices failed.');
  });

  it('stops the payment detail spinner once payment detail loads even if transactions are still loading', async () => {
    const pendingTransactions$ = new Observable<never>(() => undefined);
    billingServiceMock.loadAdminPayment.mockReturnValueOnce(of(payment));
    billingServiceMock.loadAdminPaymentTransactions.mockReturnValueOnce(pendingTransactions$);

    await component.inspectPayment(payment);

    expect(component.selectedPaymentLoading).toBe(false);
    expect(component.selectedPaymentTransactionsLoading).toBe(true);
    expect(component.selectedPayment?.uuid).toBe('payment-77');
  });

  it('renders empty payment transactions state without leaving the spinner active', async () => {
    billingServiceMock.loadAdminPayment.mockReturnValueOnce(of(payment));
    billingServiceMock.loadAdminPaymentTransactions.mockReturnValueOnce(of({
      items: [],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 0 },
    }));

    await component.inspectPayment(payment);

    expect(component.selectedPaymentLoading).toBe(false);
    expect(component.selectedPaymentTransactionsLoading).toBe(false);
    expect(component.selectedPaymentTransactions).toEqual([]);
    expect(component.selectedPaymentTransactionsMeta).toEqual({
      current_page: 1,
      last_page: 1,
      per_page: 8,
      total: 0,
    });
  });

  it('renders payment detail and transactions after the first inspect action', async () => {
    billingServiceMock.loadAdminPayment.mockReturnValueOnce(asyncValue(payment));
    billingServiceMock.loadAdminPaymentTransactions.mockReturnValueOnce(asyncValue({
      items: [paymentTransaction],
      meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 },
    }));

    await component.inspectPayment(payment);
    await fixture.whenStable();
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Payment detail');
    expect(text).toContain(payment.uuid);
    expect(text).toContain(paymentTransaction.message);
    expect(component.selectedPaymentLoading).toBe(false);
    expect(component.selectedPaymentTransactionsLoading).toBe(false);
  });

  it('shows a payment detail error and stops loading when the detail endpoint returns 404', async () => {
    billingServiceMock.loadAdminPayment.mockReturnValueOnce(throwError(() => ({
      status: 404,
      code: 'not_found',
      message: 'Payment not found.',
    })));

    await component.inspectPayment(payment);

    expect(component.selectedPaymentLoading).toBe(false);
    expect(component.selectedPaymentTransactionsLoading).toBe(false);
    expect(component.selectedPayment).toBeNull();
    expect(component.selectedPaymentError?.code).toBe('not_found');
    expect(component.selectedPaymentError?.message).toBe('Payment not found.');
  });

  it('clears list loading flags after a section error', async () => {
    billingServiceMock.loadInvoices.mockReturnValueOnce(throwError(() => ({
      status: 500,
      code: 'server',
      message: 'Invoices failed.',
    })));

    await component.loadInvoices();

    expect(component.invoicesLoading).toBe(false);
    expect(component.invoices).toEqual([]);
    expect(component.invoicesError?.message).toBe('Invoices failed.');
  });
});
