<?php

namespace App\Console\Commands\Billing;

use App\Console\Commands\BaseCommand;
use App\Models\Subscription;
use App\Services\ActivityService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Performs the Phase 18 subscription expiration check without renewal logic.
 */
class BillingCheckSubscriptionExpirationCommand extends BaseCommand
{
    protected $signature = 'billing:check-subscription-expiration {--limit=100}';

    protected $description = 'Mark safely expired subscriptions without renewal or charging.';

    private const EXPIRABLE_STATUSES = ['active', 'trialing', 'past_due', 'cancelled'];

    public function __construct(
        private readonly ActivityService $activityService,
    ) {
        parent::__construct();
    }

    /**
     * Run the scheduled subscription expiration foundation check.
     */
    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $ids = Subscription::query()
            ->whereIn('status', self::EXPIRABLE_STATUSES)
            ->where(function ($query): void {
                $query->where('current_period_end', '<=', now())
                    ->orWhere('ended_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $subscriptionId) {
            try {
                $expired = DB::transaction(function () use ($subscriptionId): ?Subscription {
                    $subscription = Subscription::query()
                        ->whereKey($subscriptionId)
                        ->lockForUpdate()
                        ->first();

                    if (! $subscription || ! $this->isStillExpirable($subscription)) {
                        return null;
                    }

                    // WHY: Phase 18 may close clearly elapsed access windows, but
                    // renewal, charging, and recovery states belong to Phase 19.
                    $subscription->forceFill([
                        'status' => 'expired',
                        'ended_at' => $subscription->ended_at ?? now(),
                        'metadata' => array_merge($subscription->metadata ?? [], [
                            'expired_by' => 'billing_scheduler',
                            'expired_checked_at' => now()->toISOString(),
                        ]),
                    ])->save();

                    return $subscription->refresh();
                });

                if (! $expired) {
                    $skipped++;
                    continue;
                }

                $this->recordActivity($expired);
                $processed++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->renderSummary([
            'limit' => $limit,
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ], 'Billing Subscription Expiration Check');

        return self::SUCCESS;
    }

    private function isStillExpirable(Subscription $subscription): bool
    {
        if (! in_array($subscription->status, self::EXPIRABLE_STATUSES, true)) {
            return false;
        }

        return ($subscription->current_period_end !== null && $subscription->current_period_end->lessThanOrEqualTo(now()))
            || ($subscription->ended_at !== null && $subscription->ended_at->lessThanOrEqualTo(now()));
    }

    private function recordActivity(Subscription $subscription): void
    {
        $this->activityService->log(null, 'billing.scheduler.subscription_expiration_check', 'Billing scheduler expired subscription', [
            'source' => 'billing_scheduler',
            'module' => 'billing',
            'subscription_id' => $subscription->id,
            'subscription_uuid' => $subscription->uuid,
            'user_id' => $subscription->user_id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
        ]);
    }
}
