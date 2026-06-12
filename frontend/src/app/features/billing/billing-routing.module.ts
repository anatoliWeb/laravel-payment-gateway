import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { BillingPortalComponent } from './pages/billing-portal/billing-portal.component';

const routes: Routes = [
  {
    path: '',
    component: BillingPortalComponent,
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class BillingRoutingModule {}
