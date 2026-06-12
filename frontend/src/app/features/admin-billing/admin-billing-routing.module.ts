import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PermissionGuard } from '../../rbac/guards/permission.guard';
import { AdminBillingDashboardPageComponent } from './pages/admin-billing-dashboard/admin-billing-dashboard-page.component';

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
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class AdminBillingRoutingModule {}
