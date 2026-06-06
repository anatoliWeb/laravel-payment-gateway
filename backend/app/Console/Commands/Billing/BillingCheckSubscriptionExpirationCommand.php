<?php

namespace App\Console\Commands\Billing;

use App\Console\Commands\BaseCommand;
use App\Models\Subscription;
use App\Services\ActivityService;
use App\Services\Billing\PaymentPreferenceService;
use App\Services\Billing\SubscriptionLifecycleService;
use Throwable;

/**
 * Performs subscription expiration and simulator-safe renewal checks.
 */
class BillingCheckSubscriptionExpirationCommand extends BaseCommand
{
    protected $signature = 'billing:check-subscription-expiration {--limit=100}';

    protected $description = 'Mark safely expired subscriptions and attempt configured simulator renewals.';

    private const EXPIRABLE_STATUSES = ['active', 'trialing', 'past_due', 'cancelled'];

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly SubscriptionLifecycleService $subscriptionLifecycleService,
        private readonly PaymentPreferenceService $paymentPreferenceService,
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
                $subscription = Subscription::query()->with('user', 'plan')->find($subscriptionId);

                if (! $subscription || ! $this->isStillExpirable($subscription)) {
                    $skipped++;
                    continue;
                }

                if ($this->shouldAttemptRenewal($subscription)) {
                    $result = $this->subscriptionLifecycleService->attemptRenewal($subscription);

                    if ($result['attempted']) {
                        $this->recordActivity($result['subscription']);
                        $processed++;
                        continue;
                    }
                }

                if ($subscription->status === 'past_due' && $subscription->ended_at === null) {
                    $skipped++;
                    continue;
                }

                // WHY: Expiration is delegated to the lifecycle service so
                // manual and scheduled paths share the same final-state behavior.
                $expired = $this->subscriptionLifecycleService->expireSubscription($subscription, 'scheduler_period_elapsed');
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

    private function shouldAttemptRenewal(Subscription $subscription): bool
    {
        if (! in_array($subscription->status, ['active', 'trialing'], true)) {
            return false;
        }

        if ((bool) data_get($subscription->metadata ?? [], 'auto_renew', false)) {
            return true;
        }

        $preference = $this->paymentPreferenceService->getOrCreatePreferences($subscription->user);

        return (bool) $preference->auto_charge_enabled
            || in_array($preference->strategy, ['wallet_only', 'wallet_first'], true);
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
