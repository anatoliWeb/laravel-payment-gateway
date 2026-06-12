import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { BillingModule } from '../../billing.module';
import { BillingIdempotencyService } from '../../services/billing-idempotency.service';
import { BillingService } from '../../services/billing.service';
import { BillingCheckoutPageComponent } from './billing-checkout-page.component';

describe('BillingCheckoutPageComponent', () => {
  let fixture: ComponentFixture<BillingCheckoutPageComponent>;
  let component: BillingCheckoutPageComponent;

  const billingServiceMock = {
    loadWallet: vi.fn().mockReturnValue(of({
      uuid: 'wallet-1',
      status: 'active',
      balances: [
        {
          currency: { code: 'USD', name: 'US Dollar', symbol: '$', decimal_precision: 2 },
          available_amount: 22000,
          held_amount: 0,
          updated_at: '2026-06-12T08:00:00Z',
        },
      ],
      created_at: '2026-06-12T07:00:00Z',
    })),
    loadPaymentMethods: vi.fn().mockReturnValue(of([
      {
        id: 101,
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
    createPayment: vi.fn().mockReturnValue(of({
      uuid: 'payment-1',
      amount: 49900,
      currency: 'USD',
      status: 'pending',
      payment_source: 'wallet_first',
      payment_method_summary: { id: 101, uuid: 'pm-1', type: 'fake_card' },
      provider: 'simulator',
      provider_reference: 'prov-1',
      invoice_id: null,
      wallet_transaction_id: null,
      created_at: '2026-06-12T08:30:00Z',
    })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BillingModule],
      providers: [
        { provide: BillingService, useValue: billingServiceMock },
        { provide: BillingIdempotencyService, useValue: { createKey: vi.fn().mockReturnValue('checkout-test-key') } },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: {
                get: (key: string) => (key === 'planSlug' ? 'pro' : null),
              },
            },
          },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(BillingCheckoutPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('renders checkout content and plan summary', () => {
    expect(fixture.nativeElement.textContent).toContain('Billing Checkout');
    expect(fixture.nativeElement.textContent).toContain('Pro');
    expect(fixture.nativeElement.textContent).toContain('Create payment');
  });

  it('sends a payment payload with idempotency key and displays payment status', async () => {
    component.checkoutForm.patchValue({
      payment_source: 'wallet_first',
      payment_strategy: 'wallet_first',
      payment_method_id: '101',
      amount: '49900',
      currency: 'USD',
      company_id: '15',
      seller_id: '42',
      description: 'Pro plan purchase',
      callback_url: 'https://example.test/callback',
    });

    await component.submit();
    fixture.detectChanges();

    expect(billingServiceMock.createPayment).toHaveBeenCalledWith(
      expect.objectContaining({
        plan_slug: 'pro',
        amount: 49900,
        currency: 'USD',
        payment_source: 'wallet_first',
        payment_strategy: 'wallet_first',
        payment_method_id: 101,
        company_id: 15,
        seller_id: 42,
      }),
      'checkout-test-key',
    );
    expect(fixture.nativeElement.textContent).toContain('Payment result');
    expect(fixture.nativeElement.textContent).toContain('pending');
  });

  it('maps field-level billing errors', () => {
    const lines = component.errorFieldLines({
      status: 422,
      code: 'validation',
      message: 'Invalid payload',
      errors: {
        amount: ['must be positive'],
        currency: ['is required'],
      },
    });

    expect(lines).toEqual([
      'amount: must be positive',
      'currency: is required',
    ]);
  });
});
