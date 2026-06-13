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
});
