import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { BillingCheckoutPageComponent } from './pages/billing-checkout/billing-checkout-page.component';
import { BillingOwnershipOverviewPageComponent } from './pages/billing-ownership-overview/billing-ownership-overview-page.component';
import { BillingPortalComponent } from './pages/billing-portal/billing-portal.component';
import { BillingDemoFlowsPageComponent } from './pages/billing-demo-flows/billing-demo-flows-page.component';
import { InvoicePaymentPageComponent } from './pages/invoice-payment/invoice-payment-page.component';
import { WalletTopUpPageComponent } from './pages/wallet-top-up/wallet-top-up-page.component';

const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    component: BillingPortalComponent,
  },
  {
    path: 'demo',
    component: BillingDemoFlowsPageComponent,
  },
  {
    path: 'company',
    component: BillingOwnershipOverviewPageComponent,
    data: {
      scope: 'company',
    },
  },
  {
    path: 'seller',
    component: BillingOwnershipOverviewPageComponent,
    data: {
      scope: 'seller',
    },
  },
  {
    path: 'checkout',
    component: BillingCheckoutPageComponent,
  },
  {
    path: 'checkout/plan/:planSlug',
    component: BillingCheckoutPageComponent,
  },
  {
    path: 'invoices/:invoiceId/pay',
    component: InvoicePaymentPageComponent,
  },
  {
    path: 'wallet/top-up',
    component: WalletTopUpPageComponent,
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class BillingRoutingModule {}
