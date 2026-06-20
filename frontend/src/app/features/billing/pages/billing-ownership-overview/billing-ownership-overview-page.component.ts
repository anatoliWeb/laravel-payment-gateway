import { Component, OnInit, inject } from '@angular/core';
import { FormBuilder } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { TranslationFacadeService } from '../../../../i18n/services/translation-facade.service';

type BillingOwnershipScope = 'company' | 'seller';

type OwnershipGapSection = {
  key: string;
  title: string;
  note: string;
  status: 'gap' | 'ready';
  columns: string[];
};

type OwnershipFilterState = {
  date_from: string;
  date_to: string;
  status: string;
  currency: string;
  seller_id: string;
  customer_id: string;
};

@Component({
  selector: 'app-billing-ownership-overview-page',
  templateUrl: './billing-ownership-overview-page.component.html',
  styleUrls: ['./billing-ownership-overview-page.component.scss'],
  standalone: false,
})
export class BillingOwnershipOverviewPageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly fb = inject(FormBuilder);
  private readonly translations = inject(TranslationFacadeService);

  readonly sections: OwnershipGapSection[] = [
    {
      key: 'payments',
      title: 'Payment history',
      note: 'The backend does not expose a company or seller payment list endpoint yet, so this remains a documented gap.',
      status: 'gap',
      columns: ['Payment', 'Status', 'Amount', 'Currency', 'Provider', 'Created', 'Ownership'],
    },
    {
      key: 'invoices',
      title: 'Invoices',
      note: 'Invoice list endpoints are currently user-scoped only, so scoped company and seller views are not live yet.',
      status: 'gap',
      columns: ['Invoice', 'Status', 'Total', 'Due', 'Payment', 'Created', 'Ownership'],
    },
    {
      key: 'customers',
      title: 'Customers',
      note: 'Company and seller customer list APIs are still missing, so this page keeps the customer panel explicit.',
      status: 'gap',
      columns: ['Customer', 'Relation', 'Last payment', 'Status', 'Created', 'Ownership'],
    },
    {
      key: 'revenue',
      title: 'Revenue summary',
      note: 'No revenue or payment summary endpoint is exposed for company or seller scope yet, so only the structure is shown.',
      status: 'gap',
      columns: ['Period', 'Gross', 'Fees', 'Net', 'Currency', 'Scope'],
    },
    {
      key: 'provider',
      title: 'Provider account status',
      note: 'Provider account readiness exists in the backend model, but there is no scoped status endpoint to render here yet.',
      status: 'gap',
      columns: ['Provider', 'Status', 'Scope', 'Secret state', 'Updated'],
    },
    {
      key: 'webhooks',
      title: 'Webhook delivery status',
      note: 'Webhook delivery history is only exposed per payment, so company and seller delivery rollups are intentionally missing.',
      status: 'gap',
      columns: ['Payment', 'Event', 'Status', 'Attempts', 'Next attempt', 'Updated'],
    },
  ];

  readonly filterForm = this.fb.group({
    date_from: [''],
    date_to: [''],
    status: [''],
    currency: [''],
    seller_id: [''],
    customer_id: [''],
  });

  appliedFilters: OwnershipFilterState | null = null;

  ngOnInit(): void {
    this.appliedFilters = this.normalizedFilters();
  }

  get scope(): BillingOwnershipScope {
    return (this.route.snapshot.data['scope'] as BillingOwnershipScope | undefined) ?? 'company';
  }

  get scopeLabel(): string {
    return this.scope === 'company'
      ? this.translations.t('billing.ownership.scopes.company')
      : this.translations.t('billing.ownership.scopes.seller');
  }

  get scopeTitle(): string {
    return this.scope === 'company'
      ? this.translations.t('billing.ownership.company.title')
      : this.translations.t('billing.ownership.seller.title');
  }

  get heroNote(): string {
    return this.scope === 'company'
      ? this.translations.t('billing.ownership.company.subtitle')
      : this.translations.t('billing.ownership.seller.subtitle');
  }

  get otherScopeRoute(): string {
    return this.scope === 'company' ? '/billing/seller' : '/billing/company';
  }

  get filterSummary(): string[] {
    const filters = this.appliedFilters ?? this.normalizedFilters();
    const entries: Array<[string, string]> = [
      [this.translations.t('billing.ownership.filters.dateFrom'), filters.date_from || this.translations.t('billing.ownership.values.any')],
      [this.translations.t('billing.ownership.filters.dateTo'), filters.date_to || this.translations.t('billing.ownership.values.any')],
      [this.translations.t('billing.ownership.filters.status'), filters.status || this.translations.t('billing.ownership.values.any')],
      [this.translations.t('billing.ownership.filters.currency'), filters.currency || this.translations.t('billing.ownership.values.any')],
      [this.translations.t('billing.ownership.filters.sellerId'), filters.seller_id || this.translations.t('billing.ownership.values.any')],
      [this.translations.t('billing.ownership.filters.customerId'), filters.customer_id || this.translations.t('billing.ownership.values.any')],
    ];

    return entries.map(([label, value]) => `${label}: ${value}`);
  }

  applyFilters(): void {
    this.appliedFilters = this.normalizedFilters();
  }

  resetFilters(): void {
    this.filterForm.reset({
      date_from: '',
      date_to: '',
      status: '',
      currency: '',
      seller_id: '',
      customer_id: '',
    });
    this.appliedFilters = this.normalizedFilters();
  }

  trackBySection(_: number, section: OwnershipGapSection): string {
    return section.key;
  }

  trackByColumn(_: number, column: string): string {
    return column;
  }

  statusClass(status: string): string {
    return status === 'ready' ? 'status--positive' : 'status--pending';
  }

  private normalizedFilters(): OwnershipFilterState {
    const raw = this.filterForm.getRawValue();

    return {
      date_from: String(raw.date_from ?? '').trim(),
      date_to: String(raw.date_to ?? '').trim(),
      status: String(raw.status ?? '').trim(),
      currency: String(raw.currency ?? '').trim().toUpperCase(),
      seller_id: String(raw.seller_id ?? '').trim(),
      customer_id: String(raw.customer_id ?? '').trim(),
    };
  }
}
