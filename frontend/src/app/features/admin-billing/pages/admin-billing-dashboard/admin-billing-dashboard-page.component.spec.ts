import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { AdminBillingModule } from '../../admin-billing.module';
import { BillingIdempotencyService } from '../../../billing/services/billing-idempotency.service';
import { BillingService } from '../../../billing/services/billing.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { AdminBillingDashboardPageComponent } from './admin-billing-dashboard-page.component';

describe('AdminBillingDashboardPageComponent', () => {
  let fixture: ComponentFixture<AdminBillingDashboardPageComponent>;
  let component: AdminBillingDashboardPageComponent;

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

  const billingServiceMock = {
    loadInvoices: vi.fn().mockReturnValue(of({ items: [invoice], meta: { current_page: 1, last_page: 1, per_page: 6, total: 1 } })),
    loadInvoice: vi.fn().mockReturnValue(of(invoice)),
    loadActivityLogs: vi.fn().mockReturnValue(of({ items: [activity], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    loadSubscription: vi.fn().mockReturnValue(of(subscription)),
    loadWebhookDeliveries: vi.fn().mockReturnValue(of({ items: [webhook], meta: { current_page: 1, last_page: 1, per_page: 8, total: 1 } })),
    retryWebhookDelivery: vi.fn().mockReturnValue(of(webhook)),
    adjustWallet: vi.fn().mockReturnValue(of(walletTransaction)),
  };

  const permissionServiceMock = {
    hasRole: vi.fn().mockReturnValue(true),
    hasPermission: vi.fn().mockReturnValue(true),
    hasAnyPermission: vi.fn().mockReturnValue(true),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AdminBillingModule, RouterTestingModule],
      providers: [
        { provide: BillingService, useValue: billingServiceMock },
        { provide: BillingIdempotencyService, useValue: { createKey: vi.fn().mockReturnValue('admin-wallet-adjustment-key') } },
        { provide: PermissionService, useValue: permissionServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AdminBillingDashboardPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('renders the admin billing dashboard and documented gaps', () => {
    expect(fixture.nativeElement.textContent).toContain('Billing Management');
    expect(fixture.nativeElement.textContent).toContain('Payments list and detail');
    expect(fixture.nativeElement.textContent).toContain('Activity logs');
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
});
