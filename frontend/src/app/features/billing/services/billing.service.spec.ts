import { of } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { BillingService } from './billing.service';

describe('BillingService', () => {
  it('loads report aggregates from the backend and forwards filters', () => {
    const getMock = vi.fn().mockReturnValue(of({
      data: {
        scope: 'revenue-summary',
        generated_at: '2026-06-13T08:00:00Z',
        filters: {
          date_from: '2026-06-01',
          date_to: '2026-06-30',
          currency: 'USD',
          company_id: 15,
          seller_id: null,
        },
        summary: {
          payment_count: 4,
          successful_payment_count: 3,
          revenue_amount: 489900,
          average_successful_payment_amount: 163300,
        },
        currency_breakdown: [],
        status_breakdown: [],
      },
    }));

    const service = new BillingService({ get: getMock } as any);

    let result: unknown = null;
    service.loadBillingRevenueSummary({
      date_from: '2026-06-01',
      date_to: '2026-06-30',
      currency: 'usd',
      company_id: 15,
      seller_id: null,
    }).subscribe((value) => {
      result = value;
    });

    expect(result).toEqual({
      scope: 'revenue-summary',
      generated_at: '2026-06-13T08:00:00Z',
      filters: {
        date_from: '2026-06-01',
        date_to: '2026-06-30',
        currency: 'USD',
        company_id: 15,
        seller_id: null,
      },
      summary: {
        payment_count: 4,
        successful_payment_count: 3,
        revenue_amount: 489900,
        average_successful_payment_amount: 163300,
      },
      currency_breakdown: [],
      status_breakdown: [],
    });

    expect(getMock).toHaveBeenCalledTimes(1);
    const [url, options] = getMock.mock.calls[0];
    expect(url).toBe('/v1/billing/admin/reports/revenue-summary');
    expect(options.params).toEqual({
      date_from: '2026-06-01',
      date_to: '2026-06-30',
      currency: 'usd',
      company_id: 15,
    });
    expect(options.params).not.toHaveProperty('seller_id');
  });

  it('uses the dedicated report endpoints for operational aggregates', () => {
    const getMock = vi.fn().mockReturnValue(of({
      data: {
        scope: 'wallet-metrics',
        generated_at: '2026-06-13T08:00:00Z',
        filters: {},
        summary: {
          wallet_count: 2,
          active_wallet_count: 2,
          suspended_wallet_count: 0,
          closed_wallet_count: 0,
          transaction_count: 7,
        },
        wallet_status_breakdown: [],
        currency_breakdown: [],
        transaction_breakdown: [],
      },
    }));

    const service = new BillingService({ get: getMock } as any);

    let result: unknown = null;
    service.loadBillingWalletMetrics({ wallet_status: 'active' }).subscribe((value) => {
      result = value;
    });

    expect(result).toEqual({
      scope: 'wallet-metrics',
      generated_at: '2026-06-13T08:00:00Z',
      filters: {},
      summary: {
        wallet_count: 2,
        active_wallet_count: 2,
        suspended_wallet_count: 0,
        closed_wallet_count: 0,
        transaction_count: 7,
      },
      wallet_status_breakdown: [],
      currency_breakdown: [],
      transaction_breakdown: [],
    });

    expect(getMock).toHaveBeenCalledWith('/v1/billing/admin/reports/wallet-metrics', expect.any(Object));
    const [, options] = getMock.mock.calls[0];
    expect(options.params).toEqual({ wallet_status: 'active' });
  });

  it('maps admin payment transactions from the paginated data/meta envelope', () => {
    const getMock = vi.fn().mockReturnValue(of({
      data: [
        {
          id: 3,
          payment_id: 4,
          type: 'payment_failed',
          status_from: 'processing',
          status_to: 'failed',
          amount: 49900,
          currency: 'USD',
          message: 'Demo payment failed.',
          payload: {
            seeded: true,
            source: 'billing_demo_seeder',
            purpose: 'payment_history',
            seed_key: 'billing_demo_idata_v2',
          },
          created_at: '2026-06-16T08:33:26.000000Z',
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 8,
        total: 1,
      },
    }));

    const service = new BillingService({ get: getMock } as any);

    let result: unknown = null;
    service.loadAdminPaymentTransactions('payment-4', 1, 8).subscribe((value) => {
      result = value;
    });

    expect(result).toEqual({
      items: [
        {
          id: 3,
          payment_id: 4,
          type: 'payment_failed',
          status_from: 'processing',
          status_to: 'failed',
          amount: 49900,
          currency: 'USD',
          message: 'Demo payment failed.',
          payload: {
            seeded: true,
            source: 'billing_demo_seeder',
            purpose: 'payment_history',
            seed_key: 'billing_demo_idata_v2',
          },
          created_at: '2026-06-16T08:33:26.000000Z',
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 8,
        total: 1,
      },
    });

    expect(getMock).toHaveBeenCalledWith('/v1/billing/admin/payments/payment-4/transactions', {
      params: { page: 1, per_page: 8 },
    });
  });
});
