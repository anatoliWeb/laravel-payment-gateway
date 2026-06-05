<?php

namespace App\Console\Commands\Billing;

use App\Console\Commands\BaseCommand;
use App\Events\Billing\PaymentExpired;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\ActivityService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Expires stale simulator-safe payment attempts without provider calls.
 */
class BillingExpirePendingPaymentsCommand extends BaseCommand
{
    protected $signature = 'billing:expire-pending-payments {--ttl-minutes=30} {--limit=100}';

    protected $description = 'Expire stale pending/processing simulator billing payments.';

    private const EXPIRABLE_STATUSES = ['pending', 'processing'];

    private const SIMULATOR_SAFE_PROVIDERS = ['simulator', 'manual', 'internal_wallet'];

    public function __construct(
        private readonly ActivityService $activityService,
    ) {
        parent::__construct();
    }

    /**
     * Run the scheduled payment expiration pass.
     */
    public function handle(): int
    {
        $ttlMinutes = max((int) $this->option('ttl-minutes'), 1);
        $limit = max((int) $this->option('limit'), 1);
        $threshold = now()->subMinutes($ttlMinutes);

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $ids = Payment::query()
            ->whereIn('status', self::EXPIRABLE_STATUSES)
            ->whereIn('provider', self::SIMULATOR_SAFE_PROVIDERS)
            ->where('created_at', '<=', $threshold)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $paymentId) {
            try {
                $expired = DB::transaction(function () use ($paymentId, $threshold): ?Payment {
                    $payment = Payment::query()
                        ->whereKey($paymentId)
                        ->lockForUpdate()
                        ->first();

                    if (! $payment || ! $this->isStillExpirable($payment, $threshold)) {
                        return null;
                    }

                    $previousStatus = $payment->status;

                    // WHY: Scheduled expiration is a financial state change, so it is
                    // protected by a row lock and an append-only transaction entry.
                    $payment->forceFill([
                        'status' => 'expired',
                        'expired_at' => now(),
                        'failure_reason' => 'payment_expired',
                    ])->save();

                    PaymentTransaction::query()->create([
                        'payment_id' => $payment->id,
                        'type' => 'payment_expired',
                        'status_from' => $previousStatus,
                        'status_to' => 'expired',
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'message' => 'Payment expired by scheduler.',
                        'payload' => [
                            'source' => 'billing_scheduler',
                            'ttl_minutes' => max((int) $this->option('ttl-minutes'), 1),
                            'provider' => $payment->provider,
                            'payer_user_id' => $payment->payer_user_id,
                            'company_id' => $payment->company_id,
                            'seller_id' => $payment->seller_id,
                        ],
                    ]);

                    return $payment->refresh();
                });

                if (! $expired) {
                    $skipped++;
                    continue;
                }

                event(new PaymentExpired($expired));
                $this->recordActivity($expired, 'billing.scheduler.expire_pending_payments');
                $processed++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->renderSummary([
            'ttl_minutes' => $ttlMinutes,
            'limit' => $limit,
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ], 'Billing Expire Pending Payments');

        return self::SUCCESS;
    }

    private function isStillExpirable(Payment $payment, \DateTimeInterface $threshold): bool
    {
        return in_array($payment->status, self::EXPIRABLE_STATUSES, true)
            && in_array($payment->provider, self::SIMULATOR_SAFE_PROVIDERS, true)
            && $payment->created_at !== null
            && $payment->created_at->lessThanOrEqualTo($threshold);
    }

    private function recordActivity(Payment $payment, string $action): void
    {
        $this->activityService->log(null, $action, 'Billing scheduler expired pending payment', [
            'source' => 'billing_scheduler',
            'module' => 'billing',
            'payment_id' => $payment->id,
            'payment_uuid' => $payment->uuid,
            'status' => $payment->status,
            'provider' => $payment->provider,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'payer_user_id' => $payment->payer_user_id,
            'company_id' => $payment->company_id,
            'seller_id' => $payment->seller_id,
        ]);
    }
}
