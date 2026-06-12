import { Component, OnInit, inject } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { BillingIdempotencyService } from '../../services/billing-idempotency.service';
import { BillingService } from '../../services/billing.service';
import type {
  BillingPaymentMethod,
  BillingPortalError,
  BillingWallet,
  BillingWalletTopUpResponse,
} from '../../models/billing.model';

@Component({
  selector: 'app-wallet-top-up-page',
  templateUrl: './wallet-top-up-page.component.html',
  styleUrls: ['./wallet-top-up-page.component.scss'],
  standalone: false,
})
export class WalletTopUpPageComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly idempotency = inject(BillingIdempotencyService);

  wallet: BillingWallet | null = null;
  walletLoading = false;
  walletError: BillingPortalError | null = null;

  paymentMethods: BillingPaymentMethod[] = [];
  paymentMethodsLoading = false;
  paymentMethodsError: BillingPortalError | null = null;

  topUpResult: BillingWalletTopUpResponse | null = null;
  topUpResultMessage: string | null = null;
  topUpActionError: BillingPortalError | null = null;
  submitting = false;

  readonly topUpForm = this.fb.group({
    amount: ['3000', [Validators.required, Validators.min(1)]],
    currency: ['USD', [Validators.required, Validators.minLength(3), Validators.maxLength(3)]],
    payment_method_id: ['', [Validators.required]],
  });

  constructor(private readonly billingService: BillingService) {}

  ngOnInit(): void {
    void this.refresh();
  }

  get selectedPaymentMethod(): BillingPaymentMethod | null {
    const id = this.parseOptionalInteger(this.topUpForm.controls.payment_method_id.value);
    return this.paymentMethods.find((method) => method.id === id) ?? this.paymentMethods.find((method) => method.is_default) ?? this.paymentMethods[0] ?? null;
  }

  async refresh(): Promise<void> {
    await Promise.allSettled([
      this.loadWallet(),
      this.loadPaymentMethods(),
    ]);
  }

  async submit(): Promise<void> {
    this.topUpActionError = null;
    this.topUpResultMessage = null;

    if (this.topUpForm.invalid) {
      this.topUpForm.markAllAsTouched();
      this.topUpActionError = {
        status: 422,
        code: 'validation',
        message: 'Please complete the wallet top-up form.',
        errors: null,
      };
      return;
    }

    const raw = this.topUpForm.getRawValue();
    const payload = {
      amount: this.parseOptionalInteger(raw.amount) ?? 0,
      currency: String(raw.currency ?? 'USD').trim().toUpperCase() || 'USD',
      payment_method_id: this.parseOptionalInteger(raw.payment_method_id),
      metadata: {
        source: 'wallet_top_up',
      },
    };

    if (!payload.payment_method_id) {
      this.topUpActionError = {
        status: 422,
        code: 'validation',
        message: 'Choose a saved simulator payment method.',
        errors: null,
      };
      return;
    }

    this.submitting = true;
    try {
      this.topUpResult = await firstValueFrom(
        this.billingService.createWalletTopUp(payload, this.idempotency.createKey('wallet-top-up')),
      );
      this.topUpResultMessage = 'Wallet top-up created successfully.';
      await this.loadWallet();
    } catch (error) {
      this.topUpActionError = BillingService.extractError(error);
    } finally {
      this.submitting = false;
    }
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

  trackById(_: number, item: BillingPaymentMethod): number {
    return item.id;
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
      const defaultMethodId = this.paymentMethods.find((method) => method.is_default)?.id ?? this.paymentMethods[0]?.id ?? null;
      this.topUpForm.patchValue({
        payment_method_id: defaultMethodId ? String(defaultMethodId) : '',
      });
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
