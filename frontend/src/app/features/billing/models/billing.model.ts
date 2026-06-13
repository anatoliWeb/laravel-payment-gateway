export type BillingSubscriptionStatus = 'active' | 'pending' | 'past_due' | 'cancelled' | 'expired' | 'trialing' | 'suspended' | string;
export type BillingPaymentStatus = 'pending' | 'processing' | 'succeeded' | 'failed' | 'expired' | 'cancelled' | string;
export type BillingInvoiceStatus = 'draft' | 'issued' | 'payment_pending' | 'paid' | 'failed' | 'void' | 'overdue' | string;
export type BillingWalletTransactionStatus = 'pending' | 'completed' | 'failed' | 'cancelled' | string;
export type BillingWalletTransactionDirection = 'credit' | 'debit' | 'neutral' | string;
export type BillingPaymentMethodStatus = 'active' | 'inactive' | 'expired' | 'revoked' | 'failed' | string;
export type BillingPaymentMethodType = 'fake_card' | 'fake_manual_invoice' | 'fake_wallet' | string;
export type BillingPaymentStrategy = 'wallet_only' | 'payment_method_only' | 'wallet_first' | 'manual_invoice';
export type BillingPaymentSource = 'wallet' | 'payment_method' | 'wallet_first';

export interface BillingCurrencyRef {
  code: string;
  name?: string | null;
  symbol?: string | null;
  decimal_precision?: number | null;
}

export interface BillingWalletBalance {
  currency: BillingCurrencyRef;
  available_amount: number;
  held_amount: number;
  updated_at: string | null;
}

export interface BillingWallet {
  uuid: string;
  status: string;
  balances: BillingWalletBalance[];
  created_at: string | null;
}

export interface BillingWalletTransaction {
  uuid: string;
  type: string;
  direction: BillingWalletTransactionDirection;
  amount: number;
  currency: string;
  status: BillingWalletTransactionStatus;
  reason: string | null;
  reference: string | null;
  payment_uuid: string | null;
  balance_available_before: number | null;
  balance_available_after: number | null;
  balance_held_before: number | null;
  balance_held_after: number | null;
  metadata: Record<string, unknown>;
  created_at: string | null;
}

export interface BillingPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface BillingPaginatedResult<TItem> {
  items: TItem[];
  meta: BillingPaginationMeta | null;
}

export interface BillingPaymentMethod {
  id: number;
  uuid: string;
  type: BillingPaymentMethodType;
  provider: string;
  status: BillingPaymentMethodStatus;
  display_name: string | null;
  brand: string | null;
  last4: string | null;
  exp_month: number | null;
  exp_year: number | null;
  is_default: boolean;
  consent_given_at: string | null;
  metadata: Record<string, unknown>;
  created_at: string | null;
  updated_at: string | null;
}

export interface BillingPaymentMethodSummary {
  id?: number | null;
  uuid?: string | null;
  type?: string | null;
}

export interface BillingPayment {
  uuid: string;
  amount: number;
  currency: string;
  status: BillingPaymentStatus;
  payment_source: BillingPaymentSource | string | null;
  payment_method_summary?: BillingPaymentMethodSummary | null;
  provider?: string | null;
  provider_reference?: string | null;
  invoice_id?: number | null;
  wallet_transaction_id?: number | string | null;
  created_at: string | null;
}

export interface BillingAdminPayment extends BillingPayment {
  id: number;
  user_id: number | null;
  payer_user_id: number | null;
  company_id: number | null;
  seller_id: number | null;
  subscription_id: number | null;
  invoice_id: number | null;
  parent_payment_id: number | null;
  provider_account_id: number | null;
  description: string | null;
  failure_reason: string | null;
  callback_url: string | null;
  metadata: Record<string, unknown>;
  ownership_metadata: Record<string, unknown> | null;
  paid_at: string | null;
  failed_at: string | null;
  expired_at: string | null;
  cancelled_at: string | null;
  transactions_count?: number | null;
  webhook_deliveries_count?: number | null;
  updated_at: string | null;
}

export interface BillingAdminPaymentTransaction {
  id: number;
  payment_id: number;
  type: string;
  status_from: string | null;
  status_to: string | null;
  amount: number;
  currency: string;
  message: string | null;
  payload: Record<string, unknown>;
  created_at: string | null;
}

export interface BillingAdminWallet extends BillingWallet {
  id: number;
  user_id: number;
  metadata: Record<string, unknown>;
  updated_at: string | null;
}

export interface BillingAdminIdempotencyKey {
  id: number;
  user_id: number;
  scope: string;
  method: string;
  endpoint: string;
  status: string;
  key_fingerprint: string;
  request_hash: string;
  response_status: number | null;
  related_type: string | null;
  related_id: number | null;
  locked_until: string | null;
  expires_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface BillingAdminProviderAccount {
  id: number;
  uuid: string;
  user_id: number | null;
  company_id: number | null;
  seller_id: number | null;
  provider: string;
  display_name: string | null;
  status: string;
  mode: string | null;
  config_source: string | null;
  public_config: Record<string, unknown>;
  capabilities: Record<string, unknown>;
  masked_credentials: Record<string, unknown>;
  last_verified_at: string | null;
  metadata: Record<string, unknown>;
  created_at: string | null;
  updated_at: string | null;
}

export interface BillingAdminRestriction {
  id: number;
  user_id: number;
  type: string;
  scope: string;
  feature_key: string | null;
  reason: string | null;
  is_active: boolean;
  starts_at: string | null;
  ends_at: string | null;
  created_by: number | null;
  metadata: Record<string, unknown>;
  created_at: string | null;
  updated_at: string | null;
}

export interface BillingAdminFeatureOverride {
  id: number;
  user_id: number;
  subscription_id: number | null;
  feature_key: string;
  value: string;
  value_type: string;
  period: string;
  reset_policy: string;
  is_enabled: boolean;
  priority: number;
  reason: string | null;
  starts_at: string | null;
  ends_at: string | null;
  created_by: number | null;
  metadata: Record<string, unknown>;
  created_at: string | null;
  updated_at: string | null;
}

export interface BillingPaymentPreference {
  strategy: BillingPaymentStrategy;
  default_payment_method?: BillingPaymentMethod | null;
  auto_charge_enabled: boolean;
  auto_charge_consent_at: string | null;
  auto_top_up_enabled: boolean;
  auto_top_up_consent_at: string | null;
  auto_top_up_threshold_amount: number | null;
  auto_top_up_amount: number | null;
  auto_top_up_currency?: BillingCurrencyRef | null;
  max_auto_top_up_per_day: number | null;
  max_auto_top_up_per_month: number | null;
  updated_at: string | null;
}

export interface BillingInvoiceItem {
  item_type: string | null;
  description: string;
  quantity: number | null;
  unit_amount: number;
  discount_amount: number | null;
  tax_amount: number | null;
  metadata: Record<string, unknown> | null;
}

export interface BillingInvoice {
  id: number;
  uuid: string;
  number: string;
  status: BillingInvoiceStatus;
  currency: string;
  subtotal_amount?: number | null;
  discount_amount?: number | null;
  tax_amount?: number | null;
  total_amount: number;
  due_amount: number;
  paid_amount: number;
  payer_user_id?: number | null;
  company_id?: number | null;
  seller_id?: number | null;
  description: string | null;
  issued_at?: string | null;
  due_at: string | null;
  payment_id: number | null;
  subscription_id: number | null;
  paid_at?: string | null;
  voided_at?: string | null;
  overdue_at?: string | null;
  created_at: string | null;
  items?: BillingInvoiceItem[];
}

export interface BillingSubscription {
  id: number;
  uuid: string;
  user_id: number;
  plan_id: number;
  plan_slug?: string | null;
  status: BillingSubscriptionStatus;
  started_at: string | null;
  current_period_start: string | null;
  current_period_end: string | null;
  trial_ends_at: string | null;
  cancelled_at: string | null;
  cancel_at_period_end: boolean;
  ended_at: string | null;
  metadata: Record<string, unknown>;
  created_at: string | null;
}

export interface BillingActivityLog {
  id: number;
  user_id: number | null;
  user: {
    id: number;
    name: string;
    email: string;
  } | null;
  action: string;
  description: string;
  meta: Record<string, unknown>;
  created_at: string | null;
}

export interface BillingWebhookDelivery {
  id: number;
  uuid: string;
  payment_id: number;
  event_type: string;
  status: string;
  attempts: number;
  max_attempts: number;
  next_attempt_at: string | null;
  delivered_at: string | null;
  failed_at: string | null;
  last_error: string | null;
  status_code: number | null;
  callback_host: string | null;
  created_at: string | null;
}

export interface BillingPaymentMethodPayload {
  type: BillingPaymentMethodType;
  brand: string;
  last4: string;
  exp_month: number | null;
  exp_year: number | null;
  display_name: string;
  metadata: Record<string, unknown>;
}

export interface BillingPaymentPayload {
  subscription_id?: number | null;
  company_id?: number | null;
  seller_id?: number | null;
  plan_slug?: string | null;
  amount?: number | null;
  currency: string;
  payment_source: BillingPaymentSource;
  payment_strategy: BillingPaymentStrategy;
  payment_method_id?: number | null;
  callback_url?: string | null;
  description?: string | null;
  metadata: Record<string, unknown>;
}

export interface BillingInvoicePaymentPayload {
  payment_source: BillingPaymentSource;
  payment_strategy: BillingPaymentStrategy;
  payment_method_id?: number | null;
  currency: string;
  callback_url?: string | null;
  description?: string | null;
  metadata: Record<string, unknown>;
}

export interface BillingWalletTopUpPayload {
  amount: number;
  currency: string;
  payment_method_id: number | null;
  metadata: Record<string, unknown>;
}

export interface BillingWalletTopUpResponse {
  payment: BillingPayment;
  wallet_transaction: BillingWalletTransaction;
}

export interface BillingWalletAdjustmentPayload {
  user_id: number;
  currency: string;
  amount: number;
  direction: 'credit' | 'debit';
  reason: string;
  description?: string | null;
  reference?: string | null;
  metadata: Record<string, unknown>;
}

export interface BillingPaymentPreferencesPayload {
  strategy: BillingPaymentStrategy;
  default_payment_method_id: number | null;
  auto_charge_enabled: boolean;
  auto_top_up_enabled: boolean;
  auto_top_up_threshold_amount: number | null;
  auto_top_up_amount: number | null;
  auto_top_up_currency: string;
  max_auto_top_up_per_day: number | null;
  max_auto_top_up_per_month: number | null;
}

export interface BillingPortalError {
  status: number | null;
  code: string | null;
  message: string;
  errors: unknown | null;
}

export interface BillingPlanReference {
  slug: string;
  name: string;
  description: string;
  priceLabel: string;
  audience: string;
  amount?: number | null;
  currency?: string;
  interval?: string | null;
  highlighted?: boolean;
  features: string[];
}

export interface BillingUsageReference {
  featureKey: string;
  usedLabel: string;
  limitLabel: string;
  remainingLabel: string;
  period: string;
}

export interface BillingCheckoutResult {
  payment: BillingPayment;
}

export interface BillingAdminActivityFilters {
  per_page?: number;
  search?: string;
  action?: string;
  user_id?: number | null;
  subject_type?: string;
  model?: string;
  date_from?: string | null;
  date_to?: string | null;
}

export interface BillingReportFilters {
  date_from?: string | null;
  date_to?: string | null;
  currency?: string | null;
  company_id?: number | null;
  seller_id?: number | null;
  user_id?: number | null;
  plan_id?: number | null;
  payment_status?: string | null;
  invoice_status?: string | null;
  subscription_status?: string | null;
  wallet_status?: string | null;
}

export interface BillingReportEnvelope {
  scope: string;
  generated_at: string | null;
  filters: BillingReportFilters;
}

export interface BillingStatusBreakdownRow {
  status: string | null;
  payment_count?: number | null;
  amount_total?: number | null;
  invoice_count?: number | null;
  total_amount?: number | null;
  paid_amount?: number | null;
  due_amount?: number | null;
  subscription_count?: number | null;
  wallet_count?: number | null;
  transaction_count?: number | null;
  credited_amount?: number | null;
  debited_amount?: number | null;
}

export interface BillingRevenueCurrencyRow {
  currency: string | null;
  payment_count: number;
  revenue_amount: number;
}

export interface BillingRevenuePlanRow {
  plan_id: number | null;
  plan_slug: string | null;
  plan_name: string | null;
  currency: string | null;
  payment_count: number;
  revenue_amount: number;
}

export interface BillingSellerCompanyRevenueRow {
  company_id: number | null;
  company_name: string | null;
  seller_id: number | null;
  seller_name: string | null;
  currency: string | null;
  payment_count: number;
  revenue_amount: number;
}

export interface BillingRevenueSummaryReport extends BillingReportEnvelope {
  summary: {
    payment_count: number;
    successful_payment_count: number;
    revenue_amount: number;
    average_successful_payment_amount: number | null;
  };
  currency_breakdown: BillingRevenueCurrencyRow[];
  status_breakdown: BillingStatusBreakdownRow[];
}

export interface BillingPaymentStatusSummaryReport extends BillingReportEnvelope {
  summary: {
    payment_count: number;
  };
  status_breakdown: BillingStatusBreakdownRow[];
}

export interface BillingRevenueByPlanReport extends BillingReportEnvelope {
  rows: BillingRevenuePlanRow[];
}

export interface BillingRevenueByCurrencyReport extends BillingReportEnvelope {
  rows: BillingRevenueCurrencyRow[];
}

export interface BillingRevenueBySellerCompanyReport extends BillingReportEnvelope {
  rows: BillingSellerCompanyRevenueRow[];
}

export interface BillingSubscriptionMetricsReport extends BillingReportEnvelope {
  summary: {
    subscription_count: number;
    active_subscription_count: number;
    trialing_subscription_count: number;
    past_due_subscription_count: number;
    cancelled_subscription_count: number;
    new_subscription_count: number;
  };
  status_breakdown: BillingStatusBreakdownRow[];
  plan_breakdown: Array<{
    plan_id: number | null;
    plan_slug: string | null;
    plan_name: string | null;
    subscription_count: number;
  }>;
}

export interface BillingInvoiceMetricsReport extends BillingReportEnvelope {
  summary: {
    invoice_count: number;
    issued_invoice_count: number;
    paid_invoice_count: number;
    void_invoice_count: number;
    overdue_invoice_count: number;
  };
  status_breakdown: Array<{
    status: string | null;
    invoice_count: number;
    total_amount: number;
    paid_amount: number;
    due_amount: number;
  }>;
  currency_breakdown: Array<{
    currency: string | null;
    invoice_count: number;
    total_amount: number;
    paid_amount: number;
    due_amount: number;
  }>;
}

export interface BillingWalletMetricsReport extends BillingReportEnvelope {
  summary: {
    wallet_count: number;
    active_wallet_count: number;
    suspended_wallet_count: number;
    closed_wallet_count: number;
    transaction_count: number;
  };
  wallet_status_breakdown: Array<{
    status: string | null;
    wallet_count: number;
  }>;
  currency_breakdown: Array<{
    currency: string | null;
    balance_count: number;
    available_amount: number;
    held_amount: number;
  }>;
  transaction_breakdown: Array<{
    type: string | null;
    direction: string | null;
    status: string | null;
    currency: string | null;
    transaction_count: number;
    credited_amount: number;
    debited_amount: number;
  }>;
}
