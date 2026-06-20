import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { BillingModule } from '../../billing.module';
import { LocaleService } from '../../../../i18n/services/locale.service';
import { BillingDemoFlowsPageComponent } from './billing-demo-flows-page.component';

describe('BillingDemoFlowsPageComponent', () => {
  let fixture: ComponentFixture<BillingDemoFlowsPageComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BillingModule, RouterTestingModule],
    }).compileComponents();

    TestBed.inject(LocaleService).setLocale('uk');
    fixture = TestBed.createComponent(BillingDemoFlowsPageComponent);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('renders the demo flow guide in Ukrainian and all requested scenarios', () => {
    const text = fixture.nativeElement.textContent as string;
    const links = Array.from(fixture.nativeElement.querySelectorAll('a') as NodeListOf<HTMLAnchorElement>).map((item) => item.textContent?.trim() ?? '');

    expect(text).toContain('Демо-сценарії білінгу');
    expect(text).toContain('Рекомендований порядок проходження');
    expect(text).toContain('Ліміти free-плану');
    expect(text).toContain('Купівля paid-плану');
    expect(text).toContain('Поповнення гаманця');
    expect(text).toContain('Оплата інвойсу');
    expect(text).toContain('Історія доставок webhook');
    expect(text).toContain('Платіж в seller/company scope');
    expect(text).not.toContain('billing.demo.title');
    expect(links).toEqual(expect.arrayContaining([
      'Портал білінгу',
      'Оформлення',
      'Поповнення гаманця',
      'Адмін білінгу',
      'Білінг компанії',
      'Білінг продавця',
    ]));
  });

  it('renders localized status labels and route links without fake totals', () => {
    const text = fixture.nativeElement.textContent as string;

    expect(text).toContain('готово');
    expect(text).toContain('частково');
    expect(text).toContain('backend gap');
    expect(text).toContain('admin-only');
    expect(text).not.toContain('revenue total');
    expect(text).not.toContain('$0');
  });
});
