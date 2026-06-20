import { ChangeDetectorRef, Component, DestroyRef, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { FormBuilder, Validators } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { LocaleService } from '../../../../i18n/services/locale.service';
import { TranslationFacadeService } from '../../../../i18n/services/translation-facade.service';
import { BillingIdempotencyService } from '../../services/billing-idempotency.service';
import { BillingService } from '../../services/billing.service';
import type {
  BillingInvoice,
  BillingInvoicePaymentPayload,
  BillingPayment,
  BillingPaymentMethod,
  BillingPaymentStrategy,
  BillingPaymentSource,
  BillingPortalError,
  BillingWallet,
} from '../../models/billing.model';

@Component({
  selector: 'app-invoice-payment-page',
  templateUrl: './invoice-payment-page.component.html',
  styleUrls: ['./invoice-payment-page.component.scss'],
  standalone: false,
})
export class InvoicePaymentPageComponent implements OnInit {
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly route = inject(ActivatedRoute);
  private readonly fb = inject(FormBuilder);
  private readonly idempotency = inject(BillingIdempotencyService);
  private readonly translations = inject(TranslationFacadeService);
  private readonly localeService = inject(LocaleService);
  private readonly destroyRef = inject(DestroyRef);

  invoice: BillingInvoice | null = null;
  invoiceLoading = false;
  invoiceError: BillingPortalError | null = null;

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

  paymentSourceOptions: Array<{ value: BillingPaymentSource; label: string }> = [];

  paymentStrategyOptions: Array<{ value: BillingPaymentStrategy; label: string }> = [];

  private localizedPaymentSources(): Array<{ value: BillingPaymentSource; label: string }> {
    return [
      { value: 'wallet', label: this.translations.t('billing.invoicePayment.paymentSources.wallet') },
      { value: 'payment_method', label: this.translations.t('billing.invoicePayment.paymentSources.paymentMethod') },
      { value: 'wallet_first', label: this.translations.t('billing.invoicePayment.paymentSources.walletFirst') },
    ];
  }

  private localizedPaymentStrategies(): Array<{ value: BillingPaymentStrategy; label: string }> {
    return [
      { value: 'wallet_only', label: this.translations.t('billing.invoicePayment.paymentStrategies.walletOnly') },
      { value: 'payment_method_only', label: this.translations.t('billing.invoicePayment.paymentStrategies.paymentMethodOnly') },
      { value: 'wallet_first', label: this.translations.t('billing.invoicePayment.paymentStrategies.walletFirst') },
    ];
  }

  readonly invoicePaymentForm = this.fb.group({
    payment_source: ['wallet_first' as BillingPaymentSource, [Validators.required]],
    payment_strategy: ['wallet_first' as BillingPaymentStrategy, [Validators.required]],
    payment_method_id: [''],
    callback_url: [''],
    description: ['Invoice payment', [Validators.maxLength(255)]],
  });

  constructor(private readonly billingService: BillingService) {}

  ngOnInit(): void {
    this.refreshLocalizedOptions();
    this.localeService.locale$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.refreshLocalizedOptions());
    void this.refresh();
  }

  get invoiceId(): number | null {
    const raw = this.route.snapshot.paramMap.get('invoiceId');
    const parsed = Number(raw);
    return Number.isFinite(parsed) ? parsed : null;
  }

  get selectedInvoiceCurrency(): string {
    return this.invoice?.currency || 'USD';
  }

  get canUseSimulationActions(): boolean {
    return false;
  }

  async refresh(): Promise<void> {
    if (!this.invoiceId) {
      this.invoiceError = {
        status: 404,
        code: 'invoice_not_found',
        message: this.translations.t('billing.invoicePayment.validation.invalidInvoiceId'),
        errors: null,
      };
      return;
    }

    await Promise.allSettled([
      this.loadInvoice(),
      this.loadWallet(),
      this.loadPaymentMethods(),
      this.loadPaymentPreferences(),
    ]);
  }

  async submit(): Promise<void> {
    this.paymentActionError = null;
    this.paymentResultMessage = null;

    if (!this.invoice) {
      this.paymentActionError = {
        status: 404,
        code: 'invoice_not_loaded',
        message: this.translations.t('billing.invoicePayment.validation.invoiceNotLoaded'),
        errors: null,
      };
      return;
    }

    if (this.invoicePaymentForm.invalid) {
      this.invoicePaymentForm.markAllAsTouched();
      this.paymentActionError = {
        status: 422,
        code: 'validation',
        message: this.translations.t('billing.invoicePayment.validation.completeForm'),
        errors: null,
      };
      return;
    }

    const raw = this.invoicePaymentForm.getRawValue();
    const idempotencyKey = this.idempotency.createKey('invoice');

    const payload: BillingInvoicePaymentPayload = {
      payment_source: raw.payment_source as BillingPaymentSource,
      payment_strategy: raw.payment_strategy as BillingPaymentStrategy,
      payment_method_id: this.parseOptionalInteger(raw.payment_method_id),
      currency: this.invoice.currency,
      callback_url: String(raw.callback_url ?? '').trim() || null,
      description: String(raw.description ?? '').trim() || `Invoice payment for ${this.invoice.number}`,
      metadata: {
        source: 'invoice_pay',
        invoice_number: this.invoice.number,
        invoice_uuid: this.invoice.uuid,
      },
    };

    this.submitting = true;
    try {
      this.paymentResult = await firstValueFrom(
        this.billingService.payInvoice(this.invoice.id, payload, idempotencyKey),
      );
      const payment = this.paymentResult;
      this.paymentResultMessage = payment?.status === 'succeeded'
        ? this.translations.t('billing.invoicePayment.messages.paymentSucceeded')
        : this.translations.t('billing.invoicePayment.messages.paymentPending');
    } catch (error) {
      this.paymentActionError = BillingService.extractError(error);
    } finally {
      this.submitting = false;
      this.syncView();
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

  walletBalanceForCurrency(currencyCode: string): number | null {
    const balance = this.wallet?.balances.find((item) => item.currency.code === currencyCode) ?? this.wallet?.balances[0] ?? null;
    return balance?.available_amount ?? null;
  }

  trackById(_: number, item: BillingPaymentMethod): number {
    return item.id;
  }

  private async loadInvoice(): Promise<void> {
    if (!this.invoiceId) {
      return;
    }

    this.invoiceLoading = true;
    this.invoiceError = null;

    try {
      this.invoice = await firstValueFrom(this.billingService.loadInvoice(this.invoiceId));
      this.syncPaymentForm();
    } catch (error) {
      this.invoice = null;
      this.invoiceError = BillingService.extractError(error);
    } finally {
      this.invoiceLoading = false;
      this.syncView();
    }
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
      this.syncView();
    }
  }

  private async loadPaymentMethods(): Promise<void> {
    this.paymentMethodsLoading = true;
    this.paymentMethodsError = null;

    try {
      this.paymentMethods = await firstValueFrom(this.billingService.loadPaymentMethods());
      this.syncPaymentForm();
    } catch (error) {
      this.paymentMethods = [];
      this.paymentMethodsError = BillingService.extractError(error);
    } finally {
      this.paymentMethodsLoading = false;
      this.syncView();
    }
  }

  private async loadPaymentPreferences(): Promise<void> {
    this.paymentPreferencesLoading = true;
    this.paymentPreferencesError = null;

    try {
      const preferences = await firstValueFrom(this.billingService.loadPaymentPreferences());
      if (!preferences) {
        return;
      }

      this.invoicePaymentForm.patchValue({
        payment_strategy: preferences.strategy,
        payment_method_id: String(preferences.default_payment_method?.id ?? ''),
      });
    } catch (error) {
      this.paymentPreferencesError = BillingService.extractError(error);
    } finally {
      this.paymentPreferencesLoading = false;
      this.syncView();
    }
  }

  private syncPaymentForm(): void {
    const preferredMethodId = this.paymentMethods.find((method) => method.is_default)?.id ?? this.paymentMethods[0]?.id ?? null;

    this.invoicePaymentForm.patchValue({
      payment_method_id: String(preferredMethodId ?? this.invoicePaymentForm.controls.payment_method_id.value ?? ''),
      description: this.invoice ? `Invoice payment for ${this.invoice.number}` : this.invoicePaymentForm.controls.description.value,
    });
  }

  private parseOptionalInteger(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
      return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  private refreshLocalizedOptions(): void {
    this.paymentSourceOptions = this.localizedPaymentSources();
    this.paymentStrategyOptions = this.localizedPaymentStrategies();
  }

  private syncView(): void {
    if (!this.destroyRef.destroyed) {
      this.cdr.markForCheck();
    }
  }
}
