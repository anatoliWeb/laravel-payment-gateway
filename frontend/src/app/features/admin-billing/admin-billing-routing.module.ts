import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PermissionGuard } from '../../rbac/guards/permission.guard';
import { AdminBillingDashboardPageComponent } from './pages/admin-billing-dashboard/admin-billing-dashboard-page.component';
import { AdminBillingReportsDashboardPageComponent } from './pages/admin-billing-reports-dashboard/admin-billing-reports-dashboard-page.component';

const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'billing',
  },
  {
    path: 'billing',
    component: AdminBillingDashboardPageComponent,
    canActivate: [PermissionGuard],
    data: {
      permissions: ['billing.payments.view', 'billing.invoices.view', 'billing.subscriptions.view', 'billing.wallets.view', 'activity.view'],
    },
  },
  {
    path: 'billing/reports',
    component: AdminBillingReportsDashboardPageComponent,
    canActivate: [PermissionGuard],
    data: {
      permissions: ['billing.reports.view'],
    },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class AdminBillingRoutingModule {}
