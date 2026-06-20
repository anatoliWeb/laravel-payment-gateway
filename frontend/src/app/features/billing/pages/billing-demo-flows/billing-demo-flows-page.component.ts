import { Component } from '@angular/core';

type DemoStatus = 'available' | 'partial' | 'backend gap' | 'admin-only' | 'planned';

type DemoLink = {
  labelKey: string;
  route?: string;
  docs?: string;
  noteKey?: string;
};

type DemoFlowCard = {
  id: string;
  order: number;
  titleKey: string;
  purposeKey: string;
  prerequisites: string[];
  steps: string[];
  links: DemoLink[];
  status: DemoStatus;
  safetyNoteKey?: string;
};

@Component({
  selector: 'app-billing-demo-flows-page',
  templateUrl: './billing-demo-flows-page.component.html',
  styleUrls: ['./billing-demo-flows-page.component.scss'],
  standalone: false,
})
export class BillingDemoFlowsPageComponent {
  readonly demoRoute = '/billing/demo';

  readonly walkthrough: Array<{ titleKey: string; anchor: string }> = [
    { titleKey: 'billing.demo.walkthrough.freePlanLimits', anchor: '#free-plan-limits' },
    { titleKey: 'billing.demo.walkthrough.paidPlanPurchase', anchor: '#paid-plan-purchase' },
    { titleKey: 'billing.demo.walkthrough.walletTopUp', anchor: '#wallet-top-up' },
    { titleKey: 'billing.demo.walkthrough.walletPayment', anchor: '#wallet-payment' },
    { titleKey: 'billing.demo.walkthrough.paymentMethodPayment', anchor: '#payment-method-payment' },
    { titleKey: 'billing.demo.walkthrough.walletFirstFallback', anchor: '#wallet-first-fallback' },
    { titleKey: 'billing.demo.walkthrough.invoicePayment', anchor: '#invoice-payment' },
    { titleKey: 'billing.demo.walkthrough.subscriptionActivation', anchor: '#subscription-activation' },
    { titleKey: 'billing.demo.walkthrough.failedPayment', anchor: '#failed-payment' },
    { titleKey: 'billing.demo.walkthrough.webhookDeliveryHistory', anchor: '#webhook-delivery-history' },
    { titleKey: 'billing.demo.walkthrough.billingRestrictionBlacklist', anchor: '#billing-restriction-blacklist' },
    { titleKey: 'billing.demo.walkthrough.featureOverride', anchor: '#feature-override' },
    { titleKey: 'billing.demo.walkthrough.sellerCompanyScopedPayment', anchor: '#seller-company-scoped-payment' },
  ];

  readonly cards: DemoFlowCard[] = [
    {
      id: 'free-plan-limits',
      order: 1,
      titleKey: 'billing.demo.cards.freePlanLimits.title',
      purposeKey: 'billing.demo.cards.freePlanLimits.purpose',
      prerequisites: [
        'billing.demo.cards.freePlanLimits.prerequisites.userSignedIn',
        'billing.demo.cards.freePlanLimits.prerequisites.portalAvailable',
        'billing.demo.cards.freePlanLimits.prerequisites.chatAvailable',
      ],
      steps: [
        'billing.demo.cards.freePlanLimits.steps.openPortal',
        'billing.demo.cards.freePlanLimits.steps.reviewCurrentPlan',
        'billing.demo.cards.freePlanLimits.steps.triggerLimit',
        'billing.demo.cards.freePlanLimits.steps.observeLimitError',
      ],
      links: [
        { labelKey: 'billing.demo.links.billingPortal', route: '/billing' },
        { labelKey: 'billing.demo.links.userPortalDocs', docs: 'docs/billing/user-portal-ui.md' },
        { labelKey: 'billing.demo.links.billingOverviewDocs', docs: 'docs/billing/overview.md' },
      ],
      status: 'partial',
      safetyNoteKey: 'billing.demo.cards.freePlanLimits.safetyNote',
    },
    {
      id: 'paid-plan-purchase',
      order: 2,
      titleKey: 'billing.demo.cards.paidPlanPurchase.title',
      purposeKey: 'billing.demo.cards.paidPlanPurchase.purpose',
      prerequisites: [
        'billing.demo.cards.paidPlanPurchase.prerequisites.checkoutReachable',
        'billing.demo.cards.paidPlanPurchase.prerequisites.walletOrMethod',
        'billing.demo.cards.paidPlanPurchase.prerequisites.idempotencyGenerated',
      ],
      steps: [
        'billing.demo.cards.paidPlanPurchase.steps.openCheckout',
        'billing.demo.cards.paidPlanPurchase.steps.choosePlan',
        'billing.demo.cards.paidPlanPurchase.steps.selectSourceStrategy',
        'billing.demo.cards.paidPlanPurchase.steps.submitOnce',
        'billing.demo.cards.paidPlanPurchase.steps.observeStatus',
      ],
      links: [
        { labelKey: 'billing.demo.links.checkout', route: '/billing/checkout' },
        { labelKey: 'billing.demo.links.checkoutDocs', docs: 'docs/billing/checkout-payment-ui.md' },
        { labelKey: 'billing.demo.links.apiDocs', docs: 'docs/billing/payment-api.md' },
      ],
      status: 'available',
    },
    {
      id: 'wallet-top-up',
      order: 3,
      titleKey: 'billing.demo.cards.walletTopUp.title',
      purposeKey: 'billing.demo.cards.walletTopUp.purpose',
      prerequisites: [
        'billing.demo.cards.walletTopUp.prerequisites.savedMethod',
        'billing.demo.cards.walletTopUp.prerequisites.pageReachable',
      ],
      steps: [
        'billing.demo.cards.walletTopUp.steps.openPage',
        'billing.demo.cards.walletTopUp.steps.enterAmount',
        'billing.demo.cards.walletTopUp.steps.chooseMethod',
        'billing.demo.cards.walletTopUp.steps.observeResult',
        'billing.demo.cards.walletTopUp.steps.returnToPortal',
      ],
      links: [
        { labelKey: 'billing.demo.links.walletTopUp', route: '/billing/wallet/top-up' },
        { labelKey: 'billing.demo.links.checkoutDocs', docs: 'docs/billing/checkout-payment-ui.md' },
        { labelKey: 'billing.demo.links.apiDocs', docs: 'docs/billing/payment-api.md' },
      ],
      status: 'available',
    },
    {
      id: 'wallet-payment',
      order: 4,
      titleKey: 'billing.demo.cards.walletPayment.title',
      purposeKey: 'billing.demo.cards.walletPayment.purpose',
      prerequisites: [
        'billing.demo.cards.walletPayment.prerequisites.sufficientBalance',
        'billing.demo.cards.walletPayment.prerequisites.checkoutReachable',
      ],
      steps: [
        'billing.demo.cards.walletPayment.steps.openCheckout',
        'billing.demo.cards.walletPayment.steps.pickWallet',
        'billing.demo.cards.walletPayment.steps.submitPayment',
        'billing.demo.cards.walletPayment.steps.observeResponse',
      ],
      links: [
        { labelKey: 'billing.demo.links.checkout', route: '/billing/checkout' },
        { labelKey: 'billing.demo.links.walletData', route: '/billing' },
        { labelKey: 'billing.demo.links.apiDocs', docs: 'docs/billing/payment-api.md' },
      ],
      status: 'available',
    },
    {
      id: 'payment-method-payment',
      order: 5,
      titleKey: 'billing.demo.cards.paymentMethodPayment.title',
      purposeKey: 'billing.demo.cards.paymentMethodPayment.purpose',
      prerequisites: [
        'billing.demo.cards.paymentMethodPayment.prerequisites.savedMethod',
        'billing.demo.cards.paymentMethodPayment.prerequisites.checkoutReachable',
      ],
      steps: [
        'billing.demo.cards.paymentMethodPayment.steps.openCheckout',
        'billing.demo.cards.paymentMethodPayment.steps.selectMethodSource',
        'billing.demo.cards.paymentMethodPayment.steps.chooseSavedMethod',
        'billing.demo.cards.paymentMethodPayment.steps.observeStatus',
      ],
      links: [
        { labelKey: 'billing.demo.links.checkout', route: '/billing/checkout' },
        { labelKey: 'billing.demo.links.portalPaymentMethods', route: '/billing' },
        { labelKey: 'billing.demo.links.checkoutDocs', docs: 'docs/billing/checkout-payment-ui.md' },
      ],
      status: 'available',
      safetyNoteKey: 'billing.demo.cards.paymentMethodPayment.safetyNote',
    },
    {
      id: 'wallet-first-fallback',
      order: 6,
      titleKey: 'billing.demo.cards.walletFirstFallback.title',
      purposeKey: 'billing.demo.cards.walletFirstFallback.purpose',
      prerequisites: [
        'billing.demo.cards.walletFirstFallback.prerequisites.checkoutReachable',
        'billing.demo.cards.walletFirstFallback.prerequisites.stateSeeded',
      ],
      steps: [
        'billing.demo.cards.walletFirstFallback.steps.openCheckout',
        'billing.demo.cards.walletFirstFallback.steps.chooseStrategy',
        'billing.demo.cards.walletFirstFallback.steps.submitWithFallback',
        'billing.demo.cards.walletFirstFallback.steps.observeStatus',
      ],
      links: [
        { labelKey: 'billing.demo.links.checkout', route: '/billing/checkout' },
        { labelKey: 'billing.demo.links.apiDocs', docs: 'docs/billing/payment-api.md' },
      ],
      status: 'partial',
      safetyNoteKey: 'billing.demo.cards.walletFirstFallback.safetyNote',
    },
    {
      id: 'invoice-payment',
      order: 7,
      titleKey: 'billing.demo.cards.invoicePayment.title',
      purposeKey: 'billing.demo.cards.invoicePayment.purpose',
      prerequisites: [
        'billing.demo.cards.invoicePayment.prerequisites.invoiceKnown',
        'billing.demo.cards.invoicePayment.prerequisites.pageReachable',
      ],
      steps: [
        'billing.demo.cards.invoicePayment.steps.openPage',
        'billing.demo.cards.invoicePayment.steps.reviewSummary',
        'billing.demo.cards.invoicePayment.steps.pickSource',
        'billing.demo.cards.invoicePayment.steps.observeState',
      ],
      links: [
        { labelKey: 'billing.demo.links.invoicePaymentRoute', noteKey: 'billing.demo.links.invoicePaymentRouteNote' },
        { labelKey: 'billing.demo.links.userPortal', route: '/billing' },
        { labelKey: 'billing.demo.links.checkoutDocs', docs: 'docs/billing/checkout-payment-ui.md' },
        { labelKey: 'billing.demo.links.invoiceDocs', docs: 'docs/billing/invoices.md' },
      ],
      status: 'partial',
      safetyNoteKey: 'billing.demo.cards.invoicePayment.safetyNote',
    },
    {
      id: 'subscription-activation',
      order: 8,
      titleKey: 'billing.demo.cards.subscriptionActivation.title',
      purposeKey: 'billing.demo.cards.subscriptionActivation.purpose',
      prerequisites: [
        'billing.demo.cards.subscriptionActivation.prerequisites.pendingScenario',
      ],
      steps: [
        'billing.demo.cards.subscriptionActivation.steps.createScenario',
        'billing.demo.cards.subscriptionActivation.steps.payPath',
        'billing.demo.cards.subscriptionActivation.steps.observeSuccess',
        'billing.demo.cards.subscriptionActivation.steps.verifyFailure',
      ],
      links: [
        { labelKey: 'billing.demo.links.billingPortal', route: '/billing' },
        { labelKey: 'billing.demo.links.checkoutDocs', docs: 'docs/billing/checkout-payment-ui.md' },
        { labelKey: 'billing.demo.links.subscriptionDocs', docs: 'docs/billing/subscription-lifecycle.md' },
      ],
      status: 'partial',
      safetyNoteKey: 'billing.demo.cards.subscriptionActivation.safetyNote',
    },
    {
      id: 'failed-payment',
      order: 9,
      titleKey: 'billing.demo.cards.failedPayment.title',
      purposeKey: 'billing.demo.cards.failedPayment.purpose',
      prerequisites: [
        'billing.demo.cards.failedPayment.prerequisites.pageReachable',
        'billing.demo.cards.failedPayment.prerequisites.failureSafe',
      ],
      steps: [
        'billing.demo.cards.failedPayment.steps.openFlow',
        'billing.demo.cards.failedPayment.steps.useFailurePath',
        'billing.demo.cards.failedPayment.steps.observeFailedStatus',
        'billing.demo.cards.failedPayment.steps.checkAdminLogs',
      ],
      links: [
        { labelKey: 'billing.demo.links.checkout', route: '/billing/checkout' },
        { labelKey: 'billing.demo.links.adminBilling', route: '/admin/billing' },
        { labelKey: 'billing.demo.links.adminDocs', docs: 'docs/billing/admin-operator-ui.md' },
        { labelKey: 'billing.demo.links.simulationDocs', docs: 'docs/billing/payment-simulation.md' },
      ],
      status: 'backend gap',
      safetyNoteKey: 'billing.demo.cards.failedPayment.safetyNote',
    },
    {
      id: 'webhook-delivery-history',
      order: 10,
      titleKey: 'billing.demo.cards.webhookDeliveryHistory.title',
      purposeKey: 'billing.demo.cards.webhookDeliveryHistory.purpose',
      prerequisites: [
        'billing.demo.cards.webhookDeliveryHistory.prerequisites.adminAccess',
        'billing.demo.cards.webhookDeliveryHistory.prerequisites.paymentKnown',
      ],
      steps: [
        'billing.demo.cards.webhookDeliveryHistory.steps.openAdmin',
        'billing.demo.cards.webhookDeliveryHistory.steps.enterPaymentId',
        'billing.demo.cards.webhookDeliveryHistory.steps.reviewRows',
        'billing.demo.cards.webhookDeliveryHistory.steps.retryFailed',
      ],
      links: [
        { labelKey: 'billing.demo.links.adminBilling', route: '/admin/billing' },
        { labelKey: 'billing.demo.links.adminDocs', docs: 'docs/billing/admin-operator-ui.md' },
        { labelKey: 'billing.demo.links.webhookDocs', docs: 'docs/billing/webhooks.md' },
      ],
      status: 'admin-only',
    },
    {
      id: 'billing-restriction-blacklist',
      order: 11,
      titleKey: 'billing.demo.cards.billingRestrictionBlacklist.title',
      purposeKey: 'billing.demo.cards.billingRestrictionBlacklist.purpose',
      prerequisites: [
        'billing.demo.cards.billingRestrictionBlacklist.prerequisites.adminScope',
      ],
      steps: [
        'billing.demo.cards.billingRestrictionBlacklist.steps.reviewDocs',
        'billing.demo.cards.billingRestrictionBlacklist.steps.noteCrudGap',
        'billing.demo.cards.billingRestrictionBlacklist.steps.useAsGuide',
      ],
      links: [
        { labelKey: 'billing.demo.links.adminBilling', route: '/admin/billing' },
        { labelKey: 'billing.demo.links.adminDocs', docs: 'docs/billing/admin-operator-ui.md' },
        { labelKey: 'billing.demo.links.billingOverviewDocs', docs: 'docs/billing/overview.md' },
      ],
      status: 'backend gap',
    },
    {
      id: 'feature-override',
      order: 12,
      titleKey: 'billing.demo.cards.featureOverride.title',
      purposeKey: 'billing.demo.cards.featureOverride.purpose',
      prerequisites: [
        'billing.demo.cards.featureOverride.prerequisites.adminScope',
      ],
      steps: [
        'billing.demo.cards.featureOverride.steps.reviewDocs',
        'billing.demo.cards.featureOverride.steps.noteCrudGap',
        'billing.demo.cards.featureOverride.steps.useAsGuide',
      ],
      links: [
        { labelKey: 'billing.demo.links.adminBilling', route: '/admin/billing' },
        { labelKey: 'billing.demo.links.adminDocs', docs: 'docs/billing/admin-operator-ui.md' },
        { labelKey: 'billing.demo.links.overridesDocs', docs: 'docs/billing/overrides.md' },
      ],
      status: 'planned',
    },
    {
      id: 'seller-company-scoped-payment',
      order: 13,
      titleKey: 'billing.demo.cards.sellerCompanyScopedPayment.title',
      purposeKey: 'billing.demo.cards.sellerCompanyScopedPayment.purpose',
      prerequisites: [
        'billing.demo.cards.sellerCompanyScopedPayment.prerequisites.scopeKnown',
        'billing.demo.cards.sellerCompanyScopedPayment.prerequisites.checkoutSupportsOwnership',
      ],
      steps: [
        'billing.demo.cards.sellerCompanyScopedPayment.steps.openOverview',
        'billing.demo.cards.sellerCompanyScopedPayment.steps.useCheckout',
        'billing.demo.cards.sellerCompanyScopedPayment.steps.observeGapNotes',
        'billing.demo.cards.sellerCompanyScopedPayment.steps.confirmNoClientRevenue',
      ],
      links: [
        { labelKey: 'billing.demo.links.companyBilling', route: '/billing/company' },
        { labelKey: 'billing.demo.links.sellerBilling', route: '/billing/seller' },
        { labelKey: 'billing.demo.links.checkout', route: '/billing/checkout' },
        { labelKey: 'billing.demo.links.ownershipDocs', docs: 'docs/billing/seller-company-ui.md' },
      ],
      status: 'partial',
      safetyNoteKey: 'billing.demo.cards.sellerCompanyScopedPayment.safetyNote',
    },
  ];

  readonly statusLegend: Array<{ label: DemoStatus; noteKey: string }> = [
    { label: 'available', noteKey: 'billing.demo.legend.available' },
    { label: 'partial', noteKey: 'billing.demo.legend.partial' },
    { label: 'backend gap', noteKey: 'billing.demo.legend.backendGap' },
    { label: 'admin-only', noteKey: 'billing.demo.legend.adminOnly' },
    { label: 'planned', noteKey: 'billing.demo.legend.planned' },
  ];

  trackById(_: number, card: DemoFlowCard): string {
    return card.id;
  }

  trackByWalkthrough(_: number, item: { titleKey: string; anchor: string }): string {
    return item.anchor;
  }

  trackByText(_: number, value: string): string {
    return value;
  }

  trackByLink(_: number, link: DemoLink): string {
    return `${link.labelKey}:${link.route ?? link.docs ?? ''}`;
  }

  statusClass(status: DemoStatus): string {
    switch (status) {
      case 'available':
        return 'status--positive';
      case 'partial':
        return 'status--pending';
      case 'admin-only':
        return 'status--neutral';
      case 'backend gap':
      case 'planned':
      default:
        return 'status--negative';
    }
  }
}
