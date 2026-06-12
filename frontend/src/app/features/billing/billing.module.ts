import { NgModule } from '@angular/core';
import { ReactiveFormsModule } from '@angular/forms';
import { SharedModule } from '../../shared/shared.module';
import { BillingRoutingModule } from './billing-routing.module';
import { BillingPortalComponent } from './pages/billing-portal/billing-portal.component';

@NgModule({
  declarations: [BillingPortalComponent],
  imports: [SharedModule, ReactiveFormsModule, BillingRoutingModule],
})
export class BillingModule {}
