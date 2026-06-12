import { Component } from '@angular/core';
import { PermissionService } from '../../../rbac/services/permission.service';

type NavItem = {
  route: string;
  labelKey?: string;
  label?: string;
  permission?: string;
  permissions?: string[];
};

@Component({
  selector: 'app-sidebar',
  templateUrl: './sidebar.component.html',
  styleUrls: ['./sidebar.component.scss'],
  standalone: false,
})
export class SidebarComponent {
  readonly navItems: NavItem[] = [
    { route: '/dashboard', labelKey: 'layout.nav.dashboard' },
    { route: '/profile', labelKey: 'layout.nav.profile' },
    { route: '/settings', labelKey: 'layout.nav.settings', permission: 'settings.view' },
    { route: '/notifications', labelKey: 'layout.nav.notifications', permissions: ['notifications.view'] },
    { route: '/billing', label: 'Billing' },
    { route: '/billing/demo', label: 'Billing demo' },
    { route: '/billing/company', label: 'Company billing' },
    { route: '/billing/seller', label: 'Seller billing' },
    { route: '/admin/billing', label: 'Billing admin', permissions: ['billing.payments.view', 'billing.invoices.view', 'billing.subscriptions.view', 'billing.wallets.view', 'activity.view'] },
    { route: '/chat', labelKey: 'layout.nav.chat', permissions: ['chat.view', 'chat.conversations.view'] },
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
