import { Component } from '@angular/core';
import { PermissionService } from '../../../rbac/services/permission.service';

type NavItem = {
  route: string;
  labelKey?: string;
  permission?: string;
  permissions?: string[];
  exact?: boolean;
};

@Component({
  selector: 'app-sidebar',
  templateUrl: './sidebar.component.html',
  styleUrls: ['./sidebar.component.scss'],
  standalone: false,
})
export class SidebarComponent {
  readonly navItems: NavItem[] = [
    { route: '/dashboard', labelKey: 'layout.nav.dashboard', exact: true },
    { route: '/profile', labelKey: 'layout.nav.profile', exact: true },
    { route: '/settings', labelKey: 'layout.nav.settings', permission: 'settings.view', exact: true },
    { route: '/notifications', labelKey: 'layout.nav.notifications', permissions: ['notifications.view'], exact: true },
    { route: '/billing', labelKey: 'layout.nav.billing', exact: true },
    { route: '/billing/demo', labelKey: 'layout.nav.billingDemo', exact: true },
    { route: '/billing/company', labelKey: 'layout.nav.billingCompany', exact: true },
    { route: '/billing/seller', labelKey: 'layout.nav.billingSeller', exact: true },
    {
      route: '/admin/billing',
      labelKey: 'layout.nav.billingAdmin',
      permissions: ['billing.payments.view', 'billing.invoices.view', 'billing.subscriptions.view', 'billing.wallets.view', 'activity.view'],
      exact: true,
    },
    { route: '/admin/billing/reports', labelKey: 'layout.nav.billingReports', permissions: ['billing.reports.view'], exact: true },
    { route: '/chat', labelKey: 'layout.nav.chat', permissions: ['chat.view', 'chat.conversations.view'], exact: true },
  ];

  constructor(private readonly permissionService: PermissionService) {}

  get visibleNavItems(): NavItem[] {
    return this.navItems.filter((item) => this.canAccess(item));
  }

  private canAccess(item: NavItem): boolean {
    if (item.permission) {
      return this.permissionService.hasPermission(item.permission);
    }

    if (item.permissions && item.permissions.length > 0) {
      return this.permissionService.hasAnyPermission(item.permissions);
    }

    return true;
  }
}
