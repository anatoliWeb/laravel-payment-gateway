import { Component, OnInit, inject } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { FormBuilder, Validators } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { BillingIdempotencyService } from '../../services/billing-idempotency.service';
import { BillingService } from '../../services/billing.service';
import type {
  BillingPayment,
  BillingPaymentMethod,
  BillingPaymentPayload,
  BillingPaymentStrategy,
  BillingPlanReference,
  BillingPortalError,
  BillingWallet,
  BillingWalletBalance,
} from '../../models/billing.model';

type CheckoutPlan = BillingPlanReference & {
  amount: number | null;
  currency: string;
  interval: string | null;
  description: string;
};

@Component({
  selector: 'app-billing-checkout-page',
  templateUrl: './billing-checkout-page.component.html',
  styleUrls: ['./billing-checkout-page.component.scss'],
  standalone: false,
})
export class BillingCheckoutPageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly fb = inject(FormBuilder);
  private readonly idempotency = inject(BillingIdempotencyService);

  readonly plans: CheckoutPlan[] = [
    {
      slug: 'free',
      name: 'Free',
      description: 'Onboarding baseline. No checkout is required for this tier.',
      priceLabel: 'Free',
      audience: 'New users',
      amount: 0,
      currency: 'USD',
      interval: 'Monthly',
      features: ['No payment required', 'Basic dashboard access', 'Trial-friendly defaults'],
    },
    {
      slug: 'basic',
      name: 'Basic',
      description: 'Entry paid tier for small teams.',
      priceLabel: 'Paid',
      audience: 'Small teams',
      amount: 19900,
      currency: 'USD',
      interval: 'Monthly',
      features: ['Higher chat limits', 'Wallet-first billing', 'Simulated invoices'],
    },
    {
      slug: 'pro',
      name: 'Pro',
      description: 'Production-like tier for growing teams.',
      priceLabel: 'Paid',
      audience: 'Growing teams',
      amount: 49900,
      currency: 'USD',
      interval: 'Monthly',
      highlighted: true,
      features: ['Advanced chat limits', 'Saved payment methods', 'Webhook-ready flows'],
    },
    {
      slug: 'enterprise',
      name: 'Enterprise',
      description: 'Custom checkout with user-entered amount because the backend has no plans catalog endpoint.',
      priceLabel: 'Custom',
      audience: 'Large teams',
      amount: null,
      currency: 'USD',
      interval: null,
      features: ['Manual amount entry', 'Company/seller context', 'Checkout preview only'],
    },
  ];

  readonly paymentSourceOptions: Array<{ value: BillingPaymentPayload['payment_source']; label: string }> = [
    { value: 'wallet', label: 'Wallet balance' },
    { value: 'payment_method', label: 'Saved simulator payment method' },
    { value: 'wallet_first', label: 'Wallet first, then payment method fallback' },
  ];

  readonly paymentStrategyOptions: Array<{ value: BillingPaymentStrategy; label: string }> = [
    { value: 'wallet_only', label: 'Wallet only' },
    { value: 'payment_method_only', label: 'Payment method only' },
    { value: 'wallet_first', label: 'Wallet first' },
  ];

  wallet: BillingWallet | null = null;
  walletLoading = false;
  walletError: BillingPortalError | null = null;

  paymentMethods: BillingPaymentMethod[] = [];
  paymentMethodsLoading = false;
  paymentMethodsError: BillingPortalError | null = null;

  paymentPreferencesLoading = false;
  paymentPreferencesError: BillingPortalError | null = null;

  paymentResult: BillingPayment | null = null;
  paymentResultMessage: string | null = null;
  paymentActionError: BillingPortalError | null = null;
  submitting = false;

  readonly checkoutForm = this.fb.group({
    plan_slug: ['pro', [Validators.required]],
    amount: ['49900', [Validators.required]],
    currency: ['USD', [Validators.required, Validators.minLength(3), Validators.maxLength(3)]],
    payment_source: ['wallet_first' as BillingPaymentPayload['payment_source'], [Validators.required]],
    payment_strategy: ['wallet_first' as BillingPaymentStrategy, [Validators.required]],
    payment_method_id: [''],
    company_id: [''],
    seller_id: [''],
    callback_url: [''],
    description: ['Pro plan purchase', [Validators.maxLength(255)]],
  });

  constructor(private readonly billingService: BillingService) {}

  ngOnInit(): void {
    this.syncPlanDefaults(this.planFromRoute());
    void this.refresh();
  }

  get selectedPlan(): CheckoutPlan {
    return this.planBySlug(this.checkoutForm.controls.plan_slug.value ?? 'pro');
  }

  get showAmountInput(): boolean {
    return this.selectedPlan.slug === 'enterprise';
  }

  get paymentAmountPreview(): number | null {
    return this.selectedPlan.amount === null
      ? this.parseOptionalInteger(this.checkoutForm.controls.amount.value)
      : this.selectedPlan.amount;
  }

  get walletBalancePreview(): BillingWalletBalance | null {
    const currencyCode = this.checkoutForm.controls.currency.value || this.selectedPlan.currency;
    return this.walletBalanceForCurrency(currencyCode);
  }

  get canUseSimulationActions(): boolean {
    return false;
  }

  async refresh(): Promise<void> {
    await Promise.allSettled([
      this.loadWallet(),
      this.loadPaymentMethods(),
    ]);
  }

  async onPlanChange(): Promise<void> {
    this.syncPlanDefaults(this.checkoutForm.controls.plan_slug.value ?? 'pro');
    await this.loadWallet();
  }

  async submit(): Promise<void> {
    this.paymentActionError = null;
    this.paymentResultMessage = null;

    if (this.checkoutForm.invalid) {
      this.checkoutForm.markAllAsTouched();
      this.paymentActionError = {
        status: 422,
        code: 'validation',
        message: 'Please complete the checkout form.',
        errors: null,
      };
      return;
    }

    const raw = this.checkoutForm.getRawValue();
    const plan = this.planBySlug(String(raw.plan_slug ?? 'pro'));
    const idempotencyKey = this.idempotency.createKey('checkout');

    const payload: BillingPaymentPayload = {
      plan_slug: plan.slug,
      amount: plan.amount === null ? this.parseOptionalInteger(raw.amount) : plan.amount,
      currency: String(raw.currency ?? plan.currency ?? 'USD').trim().toUpperCase() || 'USD',
      payment_source: raw.payment_source as BillingPaymentPayload['payment_source'],
      payment_strategy: raw.payment_strategy as BillingPaymentStrategy,
      payment_method_id: this.parseOptionalInteger(raw.payment_method_id),
      company_id: this.parseOptionalInteger(raw.company_id),
      seller_id: this.parseOptionalInteger(raw.seller_id),
      callback_url: String(raw.callback_url ?? '').trim() || null,
      description: String(raw.description ?? '').trim() || `${plan.name} plan purchase`,
      metadata: {
        source: 'checkout',
        plan_slug: plan.slug,
        plan_name: plan.name,
      },
    };

    if (payload.amount === null && plan.slug !== 'free') {
      this.paymentActionError = {
        status: 422,
        code: 'validation',
        message: 'Please enter an amount for this checkout.',
        errors: null,
      };
      return;
    }

    this.submitting = true;
    try {
      this.paymentResult = await firstValueFrom(this.billingService.createPayment(payload, idempotencyKey));
      const payment = this.paymentResult;
      this.paymentResultMessage = payment?.status === 'succeeded'
        ? 'Payment succeeded.'
        : 'Payment created and is awaiting completion.';
      await Promise.allSettled([this.loadWallet(), this.loadPaymentMethods()]);
    } catch (error) {
      this.paymentActionError = BillingService.extractError(error);
    } finally {
      this.submitting = false;
    }
  }

  trackById(_: number, item: BillingPaymentMethod): number {
    return item.id;
  }

  formatAmount(amount: number | null | undefined, currencyCode = 'USD', precision = 2): string {
    if (amount === null || amount === undefined) {
      return '-';
    }

    const normalized = amount / Math.pow(10, precision || 2);

    try {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: precision || 2,
        maximumFractionDigits: precision || 2,
      }).format(normalized) + ` ${currencyCode}`;
    } catch {
      return `${normalized.toFixed(precision || 2)} ${currencyCode}`;
    }
  }

  statusClass(status: string | null | undefined): string {
    const normalized = String(status ?? '').toLowerCase();

    if (['active', 'succeeded', 'paid', 'completed'].includes(normalized)) {
      return 'status--positive';
    }

    if (['pending', 'processing', 'payment_pending'].includes(normalized)) {
      return 'status--pending';
    }

    if (['failed', 'expired', 'cancelled', 'inactive', 'void', 'past_due'].includes(normalized)) {
      return 'status--negative';
    }

    return 'status--neutral';
  }

  errorFieldLines(error: BillingPortalError | null): string[] {
    return BillingService.describeErrorFields(error?.errors ?? null);
  }

  isSelectedPlan(plan: CheckoutPlan): boolean {
    return plan.slug === this.checkoutForm.controls.plan_slug.value;
  }

  selectPlan(planSlug: string): void {
    this.checkoutForm.patchValue({ plan_slug: planSlug });
    void this.onPlanChange();
  }

  walletBalanceForCurrency(currencyCode: string): BillingWalletBalance | null {
    if (!this.wallet) {
      return null;
    }

    return this.wallet.balances.find((balance) => balance.currency.code === currencyCode) ?? this.wallet.balances[0] ?? null;
  }

  private planFromRoute(): string {
    return this.route.snapshot.paramMap.get('planSlug') || this.checkoutForm.controls.plan_slug.value || 'pro';
  }

  private syncPlanDefaults(planSlug: string): void {
    const plan = this.planBySlug(planSlug);

    this.checkoutForm.patchValue({
      plan_slug: plan.slug,
      amount: plan.amount === null ? '' : String(plan.amount ?? ''),
      currency: plan.currency,
      description: `${plan.name} plan purchase`,
      payment_source: 'wallet_first',
      payment_strategy: 'wallet_first',
    });
  }

  private planBySlug(planSlug: string): CheckoutPlan {
    return this.plans.find((plan) => plan.slug === planSlug) ?? this.plans[2];
  }

  private async loadWallet(): Promise<void> {
    this.walletLoading = true;
    this.walletError = null;

    try {
      this.wallet = await firstValueFrom(this.billingService.loadWallet());
    } catch (error) {
      this.wallet = null;
      this.walletError = BillingService.extractError(error);
    } finally {
      this.walletLoading = false;
    }
  }

  private async loadPaymentMethods(): Promise<void> {
    this.paymentMethodsLoading = true;
    this.paymentMethodsError = null;

    try {
      this.paymentMethods = await firstValueFrom(this.billingService.loadPaymentMethods());
      if (this.paymentMethods.length > 0 && !this.checkoutForm.controls.payment_method_id.value) {
        this.checkoutForm.patchValue({ payment_method_id: String(this.paymentMethods.find((method) => method.is_default)?.id ?? this.paymentMethods[0].id) });
      }
    } catch (error) {
      this.paymentMethods = [];
      this.paymentMethodsError = BillingService.extractError(error);
    } finally {
      this.paymentMethodsLoading = false;
    }
  }

  private parseOptionalInteger(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
      return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
}
