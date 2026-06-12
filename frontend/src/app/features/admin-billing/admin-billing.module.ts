import { NgModule } from '@angular/core';
import { ReactiveFormsModule } from '@angular/forms';
import { SharedModule } from '../../shared/shared.module';
import { AdminBillingRoutingModule } from './admin-billing-routing.module';
import { AdminBillingDashboardPageComponent } from './pages/admin-billing-dashboard/admin-billing-dashboard-page.component';

@NgModule({
  declarations: [AdminBillingDashboardPageComponent],
  imports: [SharedModule, ReactiveFormsModule, AdminBillingRoutingModule],
})
export class AdminBillingModule {}
