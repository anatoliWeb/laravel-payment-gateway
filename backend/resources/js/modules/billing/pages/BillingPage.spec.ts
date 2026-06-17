import { flushPromises, shallowMount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const hasPermissionMock = vi.fn();
const hasAnyPermissionMock = vi.fn();

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    hasPermission: hasPermissionMock,
    hasAnyPermission: hasAnyPermissionMock,
  }),
}));

describe('BillingPage launchpad', () => {
  beforeEach(() => {
    vi.resetModules();
    vi.clearAllMocks();
    vi.stubEnv('VITE_DASHBOARD_URL', 'http://localhost:4200');
  });

  it('renders admin and general billing shortcuts with configured Angular URLs', async () => {
    hasPermissionMock.mockImplementation((permission: string) => permission === 'api.docs.view' || permission === 'billing.reports.view');
    hasAnyPermissionMock.mockImplementation((permissions: string[]) => {
      return permissions.includes('billing.reports.view') || permissions.includes('billing.payments.view_any');
    });

    const { default: BillingPage } = await import('./BillingPage.vue');
    const wrapper = shallowMount(BillingPage);

    await flushPromises();

    expect(wrapper.text()).toContain('Billing Control Center');
    expect(wrapper.find('[data-testid="billing-launchpad-card-admin"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="billing-launchpad-card-reports"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="billing-launchpad-card-demo"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="billing-launchpad-card-portal"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="billing-launchpad-card-company"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="billing-launchpad-card-seller"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="billing-launchpad-card-docs"]').exists()).toBe(true);

    expect(wrapper.find('[data-testid="billing-launchpad-link-admin"]').attributes('href')).toBe('http://localhost:4200/admin/billing');
    expect(wrapper.find('[data-testid="billing-launchpad-link-reports"]').attributes('href')).toBe('http://localhost:4200/admin/billing/reports');
    expect(wrapper.find('[data-testid="billing-launchpad-link-demo"]').attributes('href')).toBe('http://localhost:4200/billing/demo');
    expect(wrapper.find('[data-testid="billing-launchpad-link-portal"]').attributes('href')).toBe('http://localhost:4200/billing');
    expect(wrapper.find('[data-testid="billing-launchpad-link-company"]').attributes('href')).toBe('http://localhost:4200/billing/company');
    expect(wrapper.find('[data-testid="billing-launchpad-link-seller"]').attributes('href')).toBe('http://localhost:4200/billing/seller');
    expect(wrapper.find('[data-testid="billing-launchpad-link-docs"]').attributes('href')).toBe('/docs/api/portal');
  });

  it('shows reports shortcut only when billing.reports.view is available', async () => {
    hasPermissionMock.mockImplementation((permission: string) => permission === 'billing.reports.view');
    hasAnyPermissionMock.mockImplementation((permissions: string[]) => permissions.includes('billing.reports.view'));

    const { default: BillingPage } = await import('./BillingPage.vue');
    const wrapper = shallowMount(BillingPage);

    await flushPromises();

    expect(wrapper.find('[data-testid="billing-launchpad-card-admin"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="billing-launchpad-card-reports"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="billing-launchpad-card-demo"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="billing-launchpad-card-portal"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="billing-launchpad-card-company"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="billing-launchpad-card-seller"]').exists()).toBe(true);
  });

  it('shows empty-state note when the current permissions cannot access billing shortcuts', async () => {
    hasPermissionMock.mockReturnValue(false);
    hasAnyPermissionMock.mockReturnValue(false);

    const { default: BillingPage } = await import('./BillingPage.vue');
    const wrapper = shallowMount(BillingPage);

    await flushPromises();

    expect(wrapper.find('[data-testid="billing-launchpad-empty"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid^="billing-launchpad-card-"]').exists()).toBe(false);
  });
});
