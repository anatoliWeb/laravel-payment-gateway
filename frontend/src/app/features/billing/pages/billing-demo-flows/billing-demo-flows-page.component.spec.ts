import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { BillingModule } from '../../billing.module';
import { BillingDemoFlowsPageComponent } from './billing-demo-flows-page.component';

describe('BillingDemoFlowsPageComponent', () => {
  let fixture: ComponentFixture<BillingDemoFlowsPageComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BillingModule, RouterTestingModule],
    }).compileComponents();

    fixture = TestBed.createComponent(BillingDemoFlowsPageComponent);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('renders the demo flow guide and all requested scenarios', () => {
    const text = fixture.nativeElement.textContent as string;
    const links = Array.from(fixture.nativeElement.querySelectorAll('a') as NodeListOf<HTMLAnchorElement>).map((item) => item.textContent?.trim() ?? '');

    expect(text).toContain('Billing Demo Flows');
    expect(text).toContain('Recommended walkthrough order');
    expect(text).toContain('Free plan limits');
    expect(text).toContain('Paid plan purchase');
    expect(text).toContain('Wallet top-up');
    expect(text).toContain('Wallet-first fallback');
    expect(text).toContain('Webhook delivery history');
    expect(text).toContain('Seller/company scoped payment');
    expect(links).toEqual(expect.arrayContaining([
      'Billing portal',
      'Checkout',
      'Wallet top-up',
      'Admin billing',
      'Company billing',
      'Seller billing',
    ]));
  });

  it('renders status labels and route links without fake totals', () => {
    const text = fixture.nativeElement.textContent as string;

    expect(text).toContain('available');
    expect(text).toContain('partial');
    expect(text).toContain('backend gap');
    expect(text).toContain('admin-only');
    expect(text).not.toContain('revenue total');
    expect(text).not.toContain('$0');
  });
});
