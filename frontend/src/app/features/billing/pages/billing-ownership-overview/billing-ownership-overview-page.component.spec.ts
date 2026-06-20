import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import { RouterTestingModule } from '@angular/router/testing';
import { BillingModule } from '../../billing.module';
import { BillingOwnershipOverviewPageComponent } from './billing-ownership-overview-page.component';

function buildRoute(scope: 'company' | 'seller') {
  return {
    snapshot: {
      data: {
        scope,
      },
    },
  };
}

describe('BillingOwnershipOverviewPageComponent', () => {
  async function createFixture(scope: 'company' | 'seller'): Promise<ComponentFixture<BillingOwnershipOverviewPageComponent>> {
    TestBed.resetTestingModule();

    await TestBed.configureTestingModule({
      imports: [BillingModule, RouterTestingModule],
      providers: [
        { provide: ActivatedRoute, useValue: buildRoute(scope) },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(BillingOwnershipOverviewPageComponent);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
    return fixture;
  }

  it('renders the company ownership billing overview and gap notes', async () => {
    const fixture = await createFixture('company');

    expect(fixture.nativeElement.textContent).toContain('Company-scoped billing reporting');
    expect(fixture.nativeElement.textContent).toContain('Scope filters');
    expect(fixture.nativeElement.textContent).toContain('Payment history');
    expect(fixture.nativeElement.textContent).toContain('Revenue summary');
    expect(fixture.nativeElement.textContent).toContain('Backend coverage missing');
  });

  it('renders the seller ownership billing overview and switch navigation', async () => {
    const fixture = await createFixture('seller');

    expect(fixture.nativeElement.textContent).toContain('Seller-scoped billing reporting');
    expect(fixture.nativeElement.textContent).toContain('Company billing');
    expect(fixture.nativeElement.textContent).toContain('Webhook delivery status');
  });
});
