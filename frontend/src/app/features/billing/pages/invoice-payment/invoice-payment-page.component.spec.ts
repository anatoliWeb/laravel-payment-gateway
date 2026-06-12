import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { BillingModule } from '../../billing.module';
import { BillingIdempotencyService } from '../../services/billing-idempotency.service';
import { BillingService } from '../../services/billing.service';
import { InvoicePaymentPageComponent } from './invoice-payment-page.component';

describe('InvoicePaymentPageComponent', () => {
  let fixture: ComponentFixture<InvoicePaymentPageComponent>;
  let component: InvoicePaymentPageComponent;

  const billingServiceMock = {
    loadInvoice: vi.fn().mockReturnValue(of({
      id: 77,
      uuid: 'invoice-1',
      number: 'INV-2026-0007',
      status: 'issued',
      currency: 'USD',
      subtotal_amount: 10000,
      discount_amount: 0,
      tax_amount: 0,
      total_amount: 10000,
      paid_amount: 0,
      due_amount: 10000,
      payer_user_id: 1,
      company_id: null,
      seller_id: null,
      subscription_id: null,
      payment_id: null,
      description: 'June billing',
      issued_at: '2026-06-12T08:00:00Z',
      due_at: '2026-07-01T00:00:00Z',
      paid_at: null,
      voided_at: null,
      overdue_at: null,
      items: [],
      created_at: '2026-06-12T08:00:00Z',
    })),
    loadWallet: vi.fn().mockReturnValue(of({
      uuid: 'wallet-1',
      status: 'active',
      balances: [
        {
          currency: { code: 'USD', name: 'US Dollar', symbol: '$', decimal_precision: 2 },
          available_amount: 32000,
          held_amount: 0,
          updated_at: '2026-06-12T08:00:00Z',
        },
      ],
      created_at: '2026-06-12T07:00:00Z',
    })),
    loadPaymentMethods: vi.fn().mockReturnValue(of([
      {
        id: 201,
        uuid: 'pm-1',
        type: 'fake_card',
        provider: 'simulator',
        status: 'active',
        display_name: 'Visa ending 4242',
        brand: 'Visa',
        last4: '4242',
        exp_month: 12,
        exp_year: 2030,
        is_default: true,
        consent_given_at: '2026-06-12T08:00:00Z',
        metadata: {},
        created_at: '2026-06-12T08:00:00Z',
        updated_at: '2026-06-12T08:00:00Z',
      },
    ])),
    loadPaymentPreferences: vi.fn().mockReturnValue(of({
      strategy: 'wallet_first',
      default_payment_method: {
        id: 201,
        uuid: 'pm-1',
        type: 'fake_card',
        provider: 'simulator',
        status: 'active',
        display_name: 'Visa ending 4242',
        brand: 'Visa',
        last4: '4242',
        exp_month: 12,
        exp_year: 2030,
        is_default: true,
        consent_given_at: '2026-06-12T08:00:00Z',
        metadata: {},
        created_at: '2026-06-12T08:00:00Z',
        updated_at: '2026-06-12T08:00:00Z',
      },
      auto_charge_enabled: true,
      auto_charge_consent_at: null,
      auto_top_up_enabled: false,
      auto_top_up_consent_at: null,
      auto_top_up_threshold_amount: null,
      auto_top_up_amount: null,
      auto_top_up_currency: null,
      max_auto_top_up_per_day: null,
      max_auto_top_up_per_month: null,
      updated_at: '2026-06-12T08:00:00Z',
    })),
    payInvoice: vi.fn().mockReturnValue(of({
      uuid: 'payment-7',
      amount: 10000,
      currency: 'USD',
      status: 'pending',
      payment_source: 'wallet_first',
      payment_method_summary: { id: 201, uuid: 'pm-1', type: 'fake_card' },
      provider: 'simulator',
      provider_reference: 'prov-7',
      invoice_id: 77,
      wallet_transaction_id: null,
      created_at: '2026-06-12T08:30:00Z',
    })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BillingModule],
      providers: [
        { provide: BillingService, useValue: billingServiceMock },
        { provide: BillingIdempotencyService, useValue: { createKey: vi.fn().mockReturnValue('invoice-test-key') } },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: {
                get: (key: string) => (key === 'invoiceId' ? '77' : null),
              },
            },
          },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(InvoicePaymentPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('renders invoice summary', () => {
    expect(fixture.nativeElement.textContent).toContain('Invoice Payment');
    expect(fixture.nativeElement.textContent).toContain('INV-2026-0007');
    expect(fixture.nativeElement.textContent).toContain('Pay invoice');
  });

  it('submits invoice payment with idempotency and shows the payment status', async () => {
    component.invoicePaymentForm.patchValue({
      payment_source: 'wallet_first',
      payment_strategy: 'wallet_first',
      payment_method_id: '201',
      callback_url: 'https://example.test/callback',
      description: 'Invoice payment',
    });

    await component.submit();
    fixture.detectChanges();

    expect(billingServiceMock.payInvoice).toHaveBeenCalledWith(
      77,
      expect.objectContaining({
        payment_source: 'wallet_first',
        payment_strategy: 'wallet_first',
        payment_method_id: 201,
        currency: 'USD',
      }),
      'invoice-test-key',
    );
    expect(fixture.nativeElement.textContent).toContain('Payment result');
    expect(fixture.nativeElement.textContent).toContain('pending');
  });
});
