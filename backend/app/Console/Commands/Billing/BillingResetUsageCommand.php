<?php

namespace App\Console\Commands\Billing;

use App\Console\Commands\BaseCommand;
use App\Models\FeatureUsage;
use App\Services\ActivityService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Resets due period-based usage counters without deleting usage rows.
 */
class BillingResetUsageCommand extends BaseCommand
{
    protected $signature = 'billing:reset-usage {--limit=500}';

    protected $description = 'Reset due daily, monthly, and billing-cycle feature usage counters.';

    private const RESETTABLE_PERIODS = ['daily', 'monthly', 'billing_cycle'];

    public function __construct(
        private readonly ActivityService $activityService,
    ) {
        parent::__construct();
    }

    /**
     * Run the scheduled usage reset pass.
     */
    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $ids = FeatureUsage::query()
            ->whereIn('period', self::RESETTABLE_PERIODS)
            ->where('used', '>', 0)
            ->whereNotNull('reset_at')
            ->where('reset_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $usageId) {
            try {
                $reset = DB::transaction(function () use ($usageId): ?FeatureUsage {
                    $usage = FeatureUsage::query()
                        ->whereKey($usageId)
                        ->lockForUpdate()
                        ->first();

                    if (! $usage || ! $this->isStillResettable($usage)) {
                        return null;
                    }

                    // WHY: Scheduler resets counters in place so audit context and
                    // feature ownership remain available for later diagnostics.
                    $usage->forceFill([
                        'used' => 0,
                        'metadata' => array_merge($usage->metadata ?? [], [
                            'last_scheduler_reset_at' => now()->toISOString(),
                        ]),
                    ])->save();

                    return $usage->refresh();
                });

                if (! $reset) {
                    $skipped++;
                    continue;
                }

                $processed++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->recordActivity($processed, $skipped, $failed);
        $this->renderSummary([
            'limit' => $limit,
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ], 'Billing Reset Usage');

        return self::SUCCESS;
    }

    private function isStillResettable(FeatureUsage $usage): bool
    {
        return in_array($usage->period, self::RESETTABLE_PERIODS, true)
            && (int) $usage->used > 0
            && $usage->reset_at !== null
            && $usage->reset_at->lessThanOrEqualTo(now());
    }

    private function recordActivity(int $processed, int $skipped, int $failed): void
    {
        $this->activityService->log(null, 'billing.scheduler.reset_usage', 'Billing scheduler reset usage counters', [
            'source' => 'billing_scheduler',
            'module' => 'billing',
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }
}
