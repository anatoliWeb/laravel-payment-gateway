import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { BillingModule } from '../../billing.module';
import { BillingIdempotencyService } from '../../services/billing-idempotency.service';
import { BillingService } from '../../services/billing.service';
import { LocaleService } from '../../../../i18n/services/locale.service';
import { WalletTopUpPageComponent } from './wallet-top-up-page.component';

describe('WalletTopUpPageComponent', () => {
  let fixture: ComponentFixture<WalletTopUpPageComponent>;
  let component: WalletTopUpPageComponent;

  const billingServiceMock = {
    loadWallet: vi.fn().mockReturnValue(of({
      uuid: 'wallet-1',
      status: 'active',
      balances: [
        {
          currency: { code: 'USD', name: 'US Dollar', symbol: '$', decimal_precision: 2 },
          available_amount: 12500,
          held_amount: 0,
          updated_at: '2026-06-12T08:00:00Z',
        },
      ],
      created_at: '2026-06-12T07:00:00Z',
    })),
    loadPaymentMethods: vi.fn().mockReturnValue(of([
      {
        id: 301,
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
    createWalletTopUp: vi.fn().mockReturnValue(of({
      payment: {
        uuid: 'payment-9',
        amount: 3000,
        currency: 'USD',
        status: 'succeeded',
        payment_source: 'payment_method',
        payment_method_summary: { id: 301, uuid: 'pm-1', type: 'fake_card' },
        provider: 'simulator',
        provider_reference: 'prov-9',
        invoice_id: null,
        wallet_transaction_id: 55,
        created_at: '2026-06-12T08:30:00Z',
      },
      wallet_transaction: {
        uuid: 'tx-9',
        type: 'wallet_top_up',
        direction: 'credit',
        amount: 3000,
        currency: 'USD',
        status: 'completed',
        reason: 'manual_top_up',
        reference: 'ref-9',
        payment_uuid: 'payment-9',
        balance_available_before: 9500,
        balance_available_after: 12500,
        balance_held_before: 0,
        balance_held_after: 0,
        metadata: {},
        created_at: '2026-06-12T08:30:00Z',
      },
    })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BillingModule],
      providers: [
        { provide: BillingService, useValue: billingServiceMock },
        { provide: BillingIdempotencyService, useValue: { createKey: vi.fn().mockReturnValue('top-up-test-key') } },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: {
                get: () => null,
              },
            },
          },
        },
      ],
    }).compileComponents();

    TestBed.inject(LocaleService).setLocale('uk');
    fixture = TestBed.createComponent(WalletTopUpPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('renders wallet top-up screen', () => {
    expect(fixture.nativeElement.textContent).toContain('Поповнення гаманця');
    expect(fixture.nativeElement.textContent).toContain('Поповнити гаманець');
    expect(fixture.nativeElement.textContent).not.toContain('billing.walletTopUp.title');
  });

  it('submits a wallet top-up and shows the resulting payment status', async () => {
    component.topUpForm.patchValue({
      amount: '3000',
      currency: 'USD',
      payment_method_id: '301',
    });

    await component.submit();
    fixture.detectChanges();

    expect(billingServiceMock.createWalletTopUp).toHaveBeenCalledWith(
      expect.objectContaining({
        amount: 3000,
        currency: 'USD',
        payment_method_id: 301,
      }),
      'top-up-test-key',
    );
    expect(fixture.nativeElement.textContent).toContain('Поповнення гаманця створено успішно.');
    expect(fixture.nativeElement.textContent).toContain('succeeded');
  });
});
