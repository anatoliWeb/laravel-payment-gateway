import { Component } from '@angular/core';

type DemoStatus = 'available' | 'partial' | 'backend gap' | 'admin-only' | 'planned';

type DemoLink = {
  label: string;
  route?: string;
  docs?: string;
  note?: string;
};

type DemoFlowCard = {
  id: string;
  order: number;
  title: string;
  purpose: string;
  prerequisites: string[];
  steps: string[];
  links: DemoLink[];
  status: DemoStatus;
  safetyNote?: string;
};

@Component({
  selector: 'app-billing-demo-flows-page',
  templateUrl: './billing-demo-flows-page.component.html',
  styleUrls: ['./billing-demo-flows-page.component.scss'],
  standalone: false,
})
export class BillingDemoFlowsPageComponent {
  readonly demoRoute = '/billing/demo';

  readonly walkthrough: Array<{ title: string; anchor: string }> = [
    { title: 'Free plan limits', anchor: '#free-plan-limits' },
    { title: 'Paid plan purchase', anchor: '#paid-plan-purchase' },
    { title: 'Wallet top-up', anchor: '#wallet-top-up' },
    { title: 'Wallet payment', anchor: '#wallet-payment' },
    { title: 'Payment method payment', anchor: '#payment-method-payment' },
    { title: 'Wallet-first fallback', anchor: '#wallet-first-fallback' },
    { title: 'Invoice payment', anchor: '#invoice-payment' },
    { title: 'Subscription activation', anchor: '#subscription-activation' },
    { title: 'Failed payment', anchor: '#failed-payment' },
    { title: 'Webhook delivery history', anchor: '#webhook-delivery-history' },
    { title: 'Billing restriction / blacklist', anchor: '#billing-restriction-blacklist' },
    { title: 'Feature override', anchor: '#feature-override' },
    { title: 'Seller/company scoped payment', anchor: '#seller-company-scoped-payment' },
  ];

  readonly cards: DemoFlowCard[] = [
    {
      id: 'free-plan-limits',
      order: 1,
      title: 'Free plan limits',
      purpose: 'Show how the billing module supports product limits even before a paid checkout happens.',
      prerequisites: ['User is signed in', 'Billing portal is available', 'Chat module is available for a limit-triggering action'],
      steps: [
        'Open the billing portal.',
        'Review current plan and placeholder usage sections.',
        'Move to the related chat UI and perform an action that reaches a configured limit if the environment is seeded that way.',
        'Observe the stable limit error and, where available, the corresponding activity log entry.',
      ],
      links: [
        { label: 'Billing portal', route: '/billing' },
        { label: 'User portal docs', docs: 'docs/billing/user-portal-ui.md' },
        { label: 'Billing overview docs', docs: 'docs/billing/overview.md' },
      ],
      status: 'partial',
      safetyNote: 'This is a demo walkthrough, not a live monetization test. No revenue values are shown.',
    },
    {
      id: 'paid-plan-purchase',
      order: 2,
      title: 'Paid plan purchase',
      purpose: 'Demonstrate the checkout page and idempotent payment creation flow for a paid plan.',
      prerequisites: ['Billing checkout page is reachable', 'Wallet or payment method is available', 'Idempotency key is generated client-side'],
      steps: [
        'Open the checkout page.',
        'Choose a plan reference.',
        'Select a payment source and strategy.',
        'Submit once and avoid duplicate clicks thanks to the client-side idempotency protection.',
        'Observe the returned payment status.',
      ],
      links: [
        { label: 'Checkout', route: '/billing/checkout' },
        { label: 'Checkout docs', docs: 'docs/billing/checkout-payment-ui.md' },
        { label: 'API docs', docs: 'docs/billing/payment-api.md' },
      ],
      status: 'available',
    },
    {
      id: 'wallet-top-up',
      order: 3,
      title: 'Wallet top-up',
      purpose: 'Show the simulator-safe wallet top-up flow and resulting wallet transaction.',
      prerequisites: ['Saved simulator payment method exists', 'Wallet top-up page is reachable'],
      steps: [
        'Open the wallet top-up page.',
        'Enter amount and currency.',
        'Choose a saved simulator payment method.',
        'Submit and observe the payment plus wallet transaction result.',
        'Return to the billing portal and confirm the wallet section still renders correctly.',
      ],
      links: [
        { label: 'Wallet top-up', route: '/billing/wallet/top-up' },
        { label: 'Checkout docs', docs: 'docs/billing/checkout-payment-ui.md' },
        { label: 'API docs', docs: 'docs/billing/payment-api.md' },
      ],
      status: 'available',
    },
    {
      id: 'wallet-payment',
      order: 4,
      title: 'Wallet payment',
      purpose: 'Show a checkout payment that debits internal wallet balance.',
      prerequisites: ['Wallet has sufficient balance', 'Checkout page is reachable'],
      steps: [
        'Open the checkout page.',
        'Pick wallet as the payment source.',
        'Submit the payment.',
        'Observe a succeeded or validation/insufficient-balance response depending on wallet state.',
      ],
      links: [
        { label: 'Checkout', route: '/billing/checkout' },
        { label: 'Wallet data', route: '/billing' },
        { label: 'API docs', docs: 'docs/billing/payment-api.md' },
      ],
      status: 'available',
    },
    {
      id: 'payment-method-payment',
      order: 5,
      title: 'Payment method payment',
      purpose: 'Show a checkout payment that uses a saved simulator payment method.',
      prerequisites: ['Saved simulator payment method exists', 'Checkout page is reachable'],
      steps: [
        'Open checkout.',
        'Select payment method as the payment source.',
        'Choose a saved simulator card-like method.',
        'Submit and observe the payment status response.',
      ],
      links: [
        { label: 'Checkout', route: '/billing/checkout' },
        { label: 'Portal payment methods', route: '/billing' },
        { label: 'Checkout docs', docs: 'docs/billing/checkout-payment-ui.md' },
      ],
      status: 'available',
      safetyNote: 'No raw card data is collected. Only simulator-safe payment method metadata is used.',
    },
    {
      id: 'wallet-first-fallback',
      order: 6,
      title: 'Wallet-first fallback',
      purpose: 'Show the mixed payment strategy where wallet is tried before a fallback source.',
      prerequisites: ['Checkout page is reachable', 'Wallet and/or payment method state is seeded'],
      steps: [
        'Open checkout.',
        'Choose wallet-first strategy.',
        'Submit with a payment method fallback available.',
        'Observe the final payment status and note that fallback behavior is server-controlled.',
      ],
      links: [
        { label: 'Checkout', route: '/billing/checkout' },
        { label: 'API docs', docs: 'docs/billing/payment-api.md' },
      ],
      status: 'partial',
      safetyNote: 'The UI does not invent fallback behavior; it only surfaces the server response.',
    },
    {
      id: 'invoice-payment',
      order: 7,
      title: 'Invoice payment',
      purpose: 'Show the invoice payment flow from invoice summary to paid or pending response.',
      prerequisites: ['A target invoice id is known', 'Invoice payment page is reachable'],
      steps: [
        'Open the invoice payment page for a specific invoice id.',
        'Review invoice summary first.',
        'Pick a payment source and strategy.',
        'Submit payment and observe the returned payment state.',
      ],
      links: [
        { label: 'Invoice payment route', note: 'Use /billing/invoices/:invoiceId/pay with a real invoice id from the portal.' },
        { label: 'User portal', route: '/billing' },
        { label: 'Checkout docs', docs: 'docs/billing/checkout-payment-ui.md' },
        { label: 'Invoice docs', docs: 'docs/billing/invoices.md' },
      ],
      status: 'partial',
      safetyNote: 'Invoice discovery is still user-scoped rather than a dedicated demo list view.',
    },
    {
      id: 'subscription-activation',
      order: 8,
      title: 'Subscription activation',
      purpose: 'Show the payment-to-subscription lifecycle relationship documented by the backend.',
      prerequisites: ['A pending subscription scenario exists in the seeded environment or test data'],
      steps: [
        'Create or reuse a pending subscription scenario.',
        'Pay the linked payment path.',
        'Observe that a successful payment activates the subscription.',
        'Verify that a failed payment does not activate it.',
      ],
      links: [
        { label: 'Billing portal', route: '/billing' },
        { label: 'Checkout docs', docs: 'docs/billing/checkout-payment-ui.md' },
        { label: 'Subscription docs', docs: 'docs/billing/subscription-lifecycle.md' },
      ],
      status: 'partial',
      safetyNote: 'The UI documents the lifecycle; it does not fabricate a hidden subscription-creation workflow.',
    },
    {
      id: 'failed-payment',
      order: 9,
      title: 'Failed payment',
      purpose: 'Show the safe failure path and how the review flow handles non-success payment states.',
      prerequisites: ['Checkout or invoice payment page is reachable', 'Failure simulation is exposed only where safe'],
      steps: [
        'Open checkout or invoice payment.',
        'Use a safe failure path if the environment exposes one.',
        'Observe the failed status and error envelope.',
        'Check admin/operator views or logs if the environment is configured for it.',
      ],
      links: [
        { label: 'Checkout', route: '/billing/checkout' },
        { label: 'Admin billing', route: '/admin/billing' },
        { label: 'Admin docs', docs: 'docs/billing/admin-operator-ui.md' },
        { label: 'Simulation docs', docs: 'docs/billing/payment-simulation.md' },
      ],
      status: 'backend gap',
      safetyNote: 'The user-facing UI does not expose hidden simulator failure controls unless they are already safe in the current product surface.',
    },
    {
      id: 'webhook-delivery-history',
      order: 10,
      title: 'Webhook delivery history',
      purpose: 'Show the admin path for viewing webhook deliveries and retrying failed rows.',
      prerequisites: ['Admin/operator access is available', 'A payment id with webhook deliveries exists'],
      steps: [
        'Open the admin billing dashboard.',
        'Enter a payment id in the webhook lookup form.',
        'Review the delivery history rows.',
        'Retry only failed rows when permissions allow it.',
      ],
      links: [
        { label: 'Admin billing', route: '/admin/billing' },
        { label: 'Admin docs', docs: 'docs/billing/admin-operator-ui.md' },
        { label: 'Webhook docs', docs: 'docs/billing/webhooks.md' },
      ],
      status: 'admin-only',
    },
    {
      id: 'billing-restriction-blacklist',
      order: 11,
      title: 'Billing restriction / blacklist',
      purpose: 'Document the planned restriction workflow without pretending CRUD exists in the UI.',
      prerequisites: ['Admin/operator scope would be required once backend CRUD exists'],
      steps: [
        'Review the admin/operator billing docs.',
        'Note that restriction and blacklist CRUD is intentionally not exposed in the current UI.',
        'Use this page as a guide for the intended future flow.',
      ],
      links: [
        { label: 'Admin billing', route: '/admin/billing' },
        { label: 'Admin docs', docs: 'docs/billing/admin-operator-ui.md' },
        { label: 'Overview docs', docs: 'docs/billing/overview.md' },
      ],
      status: 'backend gap',
    },
    {
      id: 'feature-override',
      order: 12,
      title: 'Feature override',
      purpose: 'Document the override story without inventing the missing CRUD surface.',
      prerequisites: ['Admin/operator scope would be required once backend CRUD exists'],
      steps: [
        'Review the admin/operator billing docs.',
        'Note that feature override CRUD remains a backend gap.',
        'Use the page as a portfolio guide, not as an implementation claim.',
      ],
      links: [
        { label: 'Admin billing', route: '/admin/billing' },
        { label: 'Admin docs', docs: 'docs/billing/admin-operator-ui.md' },
        { label: 'Overrides docs', docs: 'docs/billing/overrides.md' },
      ],
      status: 'planned',
    },
    {
      id: 'seller-company-scoped-payment',
      order: 13,
      title: 'Seller/company scoped payment',
      purpose: 'Show the ownership-aware payment path and its current front-end reporting gaps.',
      prerequisites: ['Company or seller scope is known', 'Checkout supports ownership fields'],
      steps: [
        'Open company billing or seller billing overview.',
        'Use checkout and supply company or seller ownership context where appropriate.',
        'Observe the explicit gap notes for reporting/history.',
        'Confirm that the UI does not calculate revenue client-side.',
      ],
      links: [
        { label: 'Company billing', route: '/billing/company' },
        { label: 'Seller billing', route: '/billing/seller' },
        { label: 'Checkout', route: '/billing/checkout' },
        { label: 'Ownership docs', docs: 'docs/billing/seller-company-ui.md' },
      ],
      status: 'partial',
      safetyNote: 'Ownership-aware views are intentionally conservative until dedicated backend reporting endpoints exist.',
    },
  ];

  readonly statusLegend: Array<{ label: DemoStatus; note: string }> = [
    { label: 'available', note: 'Implemented and safe to demo from the current UI' },
    { label: 'partial', note: 'Some pieces are live, but discovery or lifecycle is still incomplete' },
    { label: 'backend gap', note: 'The UI is a guide only because the backend route or data is missing' },
    { label: 'admin-only', note: 'Available only from the operator surface' },
    { label: 'planned', note: 'Documented for the roadmap, not yet fully wired' },
  ];

  trackById(_: number, card: DemoFlowCard): string {
    return card.id;
  }

  trackByWalkthrough(_: number, item: { title: string; anchor: string }): string {
    return item.anchor;
  }

  trackByText(_: number, value: string): string {
    return value;
  }

  trackByLink(_: number, link: DemoLink): string {
    return `${link.label}:${link.route ?? link.docs ?? ''}`;
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
