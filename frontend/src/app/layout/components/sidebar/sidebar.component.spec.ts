import { Component } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { RouterTestingModule } from '@angular/router/testing';
import { vi } from 'vitest';
import { LocaleService } from '../../../i18n/services/locale.service';
import { LayoutModule } from '../../layout.module';
import { PermissionService } from '../../../rbac/services/permission.service';
import { SidebarComponent } from './sidebar.component';

@Component({
  template: '',
  standalone: false,
})
class DummyRouteComponent {}

describe('SidebarComponent', () => {
  async function createFixture(): Promise<{ fixture: ComponentFixture<SidebarComponent>; router: Router }> {
    window.localStorage.removeItem('dashboard_locale');

    TestBed.resetTestingModule();
    await TestBed.configureTestingModule({
      declarations: [DummyRouteComponent],
      imports: [
        LayoutModule,
        RouterTestingModule.withRoutes([
          { path: 'dashboard', component: DummyRouteComponent },
          { path: 'profile', component: DummyRouteComponent },
          { path: 'settings', component: DummyRouteComponent },
          { path: 'notifications', component: DummyRouteComponent },
          { path: 'billing', component: DummyRouteComponent },
          { path: 'billing/demo', component: DummyRouteComponent },
          { path: 'billing/company', component: DummyRouteComponent },
          { path: 'billing/seller', component: DummyRouteComponent },
          { path: 'admin/billing', component: DummyRouteComponent },
          { path: 'admin/billing/reports', component: DummyRouteComponent },
          { path: 'chat', component: DummyRouteComponent },
        ]),
      ],
      providers: [
        {
          provide: PermissionService,
          useValue: {
            hasPermission: vi.fn().mockReturnValue(true),
            hasAnyPermission: vi.fn().mockReturnValue(true),
          },
        },
      ],
    }).compileComponents();

    const localeService = TestBed.inject(LocaleService);
    localeService.setLocale('uk');

    const fixture = TestBed.createComponent(SidebarComponent);
    const router = TestBed.inject(Router);

    fixture.detectChanges();
    await fixture.whenStable();

    return { fixture, router };
  }

  function navText(fixture: ComponentFixture<SidebarComponent>): string {
    return (fixture.nativeElement.textContent as string).replace(/\s+/g, ' ');
  }

  it('renders Ukrainian billing labels and does not expose raw translation keys', async () => {
    const { fixture } = await createFixture();
    const text = navText(fixture);

    expect(text).toContain('Білінг');
    expect(text).toContain('Демо білінгу');
    expect(text).toContain('Білінг компанії');
    expect(text).toContain('Білінг продавця');
    expect(text).toContain('Адмін білінгу');
    expect(text).toContain('Звіти білінгу');
    expect(text).toContain('Чат');
    expect(text).not.toContain('layout.nav.chat');
  });

  it('marks only billing admin as active on /admin/billing', async () => {
    const { fixture, router } = await createFixture();

    await router.navigateByUrl('/admin/billing');
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const activeLinks = Array.from(fixture.nativeElement.querySelectorAll('a.is-active')) as HTMLAnchorElement[];
    expect(activeLinks).toHaveLength(1);
    expect(activeLinks[0].textContent?.trim()).toBe('Адмін білінгу');
  });

  it('marks only billing reports as active on /admin/billing/reports', async () => {
    const { fixture, router } = await createFixture();

    await router.navigateByUrl('/admin/billing/reports');
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const activeLinks = Array.from(fixture.nativeElement.querySelectorAll('a.is-active')) as HTMLAnchorElement[];
    expect(activeLinks).toHaveLength(1);
    expect(activeLinks[0].textContent?.trim()).toBe('Звіти білінгу');
  });
});
