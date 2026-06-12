import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BillingModule } from '../../billing.module';
import { BillingService } from '../../services/billing.service';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { BillingPortalComponent } from './billing-portal.component';

describe('BillingPortalComponent', () => {
  let fixture: ComponentFixture<BillingPortalComponent>;
  let component: BillingPortalComponent;

  const billingServiceMock = {
    loadWallet: vi.fn().mockReturnValue(of({
      uuid: 'wallet-1',
      status: 'active',
      balances: [
        {
          currency: {
            code: 'USD',
            name: 'US Dollar',
            symbol: '$',
            decimal_precision: 2,
          },
          available_amount: 12500,
          held_amount: 1500,
          updated_at: '2026-06-12T08:00:00Z',
        },
      ],
      created_at: '2026-06-12T07:00:00Z',
    })),
    loadWalletTransactions: vi.fn().mockReturnValue(of({
      items: [
        {
          uuid: 'tx-1',
          type: 'wallet_top_up',
          direction: 'credit',
          amount: 5000,
          currency: 'USD',
          status: 'completed',
          reason: 'demo top up',
          reference: 'ref-1',
          payment_uuid: 'payment-1',
          balance_available_before: 7500,
          balance_available_after: 12500,
          balance_held_before: 0,
          balance_held_after: 1500,
          metadata: {},
          created_at: '2026-06-12T08:15:00Z',
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 1,
      },
    })),
    loadInvoices: vi.fn().mockReturnValue(of({
      items: [
        {
          id: 11,
          uuid: 'invoice-1',
          number: 'INV-2026-0001',
          status: 'paid',
          currency: 'USD',
          total_amount: 10000,
          due_amount: 0,
          paid_amount: 10000,
          description: 'June subscription',
          due_at: '2026-06-20T00:00:00Z',
          payment_id: 55,
          subscription_id: 77,
          created_at: '2026-06-12T08:20:00Z',
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 5,
        total: 1,
      },
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
    loadPaymentPreferences: vi.fn().mockReturnValue(of({
      strategy: 'wallet_first',
      default_payment_method: {
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
      auto_charge_enabled: true,
      auto_charge_consent_at: '2026-06-12T08:00:00Z',
      auto_top_up_enabled: true,
      auto_top_up_consent_at: '2026-06-12T08:00:00Z',
      auto_top_up_threshold_amount: 500,
      auto_top_up_amount: 3000,
      auto_top_up_currency: {
        code: 'USD',
        name: 'US Dollar',
        symbol: '$',
        decimal_precision: 2,
      },
      max_auto_top_up_per_day: 2,
      max_auto_top_up_per_month: 10,
      updated_at: '2026-06-12T08:00:00Z',
    })),
    createPaymentMethod: vi.fn().mockReturnValue(of({})),
    setDefaultPaymentMethod: vi.fn().mockReturnValue(of({})),
    deactivatePaymentMethod: vi.fn().mockReturnValue(of({})),
    updatePaymentPreferences: vi.fn().mockReturnValue(of({})),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BillingModule],
      providers: [
        { provide: BillingService, useValue: billingServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(BillingPortalComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('renders live billing sections and placeholder sections', () => {
    expect(fixture.nativeElement.textContent).toContain('Billing Portal');
    expect(fixture.nativeElement.textContent).toContain('Current subscription');
    expect(fixture.nativeElement.textContent).toContain('Available plans');
    expect(fixture.nativeElement.textContent).toContain('Wallet');
    expect(fixture.nativeElement.textContent).toContain('Payment methods');
    expect(fixture.nativeElement.textContent).toContain('What is not wired yet');
  });

  it('creates a simulator payment method through the billing service', async () => {
    component.paymentMethodForm.patchValue({
      type: 'fake_card',
      brand: 'Mastercard',
      last4: '1234',
      exp_month: '11',
      exp_year: '2031',
      display_name: 'Mastercard ending 1234',
    });

    await component.createPaymentMethod();

    expect(billingServiceMock.createPaymentMethod).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'fake_card',
        brand: 'Mastercard',
        last4: '1234',
        exp_month: 11,
        exp_year: 2031,
        display_name: 'Mastercard ending 1234',
        metadata: expect.objectContaining({
          source: 'billing_portal',
          simulator_safe: true,
        }),
      }),
    );
    expect(component.paymentMethodActionMessage).toBe('Payment method created successfully.');
  });

  it('saves billing preferences through the billing service', async () => {
    component.paymentPreferencesForm.patchValue({
      strategy: 'wallet_only',
      default_payment_method_id: '101',
      auto_charge_enabled: false,
      auto_top_up_enabled: true,
      auto_top_up_threshold_amount: '250',
      auto_top_up_amount: '5000',
      auto_top_up_currency: 'USD',
      max_auto_top_up_per_day: '1',
      max_auto_top_up_per_month: '4',
    });

    await component.savePaymentPreferences();

    expect(billingServiceMock.updatePaymentPreferences).toHaveBeenCalledWith(
      expect.objectContaining({
        strategy: 'wallet_only',
        default_payment_method_id: 101,
        auto_charge_enabled: false,
        auto_top_up_enabled: true,
        auto_top_up_threshold_amount: 250,
        auto_top_up_amount: 5000,
        auto_top_up_currency: 'USD',
        max_auto_top_up_per_day: 1,
        max_auto_top_up_per_month: 4,
      }),
    );
    expect(component.paymentPreferencesMessage).toBe('Payment preferences saved.');
  });
});
