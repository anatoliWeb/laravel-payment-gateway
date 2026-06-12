import { NgModule } from '@angular/core';
import { ReactiveFormsModule } from '@angular/forms';
import { SharedModule } from '../../shared/shared.module';
import { BillingRoutingModule } from './billing-routing.module';
import { BillingCheckoutPageComponent } from './pages/billing-checkout/billing-checkout-page.component';
import { BillingDemoFlowsPageComponent } from './pages/billing-demo-flows/billing-demo-flows-page.component';
import { BillingOwnershipOverviewPageComponent } from './pages/billing-ownership-overview/billing-ownership-overview-page.component';
import { InvoicePaymentPageComponent } from './pages/invoice-payment/invoice-payment-page.component';
import { BillingPortalComponent } from './pages/billing-portal/billing-portal.component';
import { WalletTopUpPageComponent } from './pages/wallet-top-up/wallet-top-up-page.component';

@NgModule({
  declarations: [
    BillingPortalComponent,
    BillingDemoFlowsPageComponent,
    BillingOwnershipOverviewPageComponent,
    BillingCheckoutPageComponent,
    InvoicePaymentPageComponent,
    WalletTopUpPageComponent,
  ],
  imports: [SharedModule, ReactiveFormsModule, BillingRoutingModule],
})
export class BillingModule {}
