<?php

namespace App\Console\Commands\Billing;

use App\Console\Commands\BaseCommand;
use App\Models\IdempotencyKey;
use App\Models\WebhookDelivery;
use App\Services\ActivityService;

/**
 * Cleans non-ledger billing operational data under explicit retention windows.
 */
class BillingCleanupCommand extends BaseCommand
{
    protected $signature = 'billing:cleanup
        {--dry-run : Report cleanup candidates without modifying records}
        {--limit=500}
        {--idempotency-retention-days=7}
        {--webhook-response-retention-days=30}';

    protected $description = 'Clean safe billing runtime data without deleting financial ledgers.';

    public function __construct(
        private readonly ActivityService $activityService,
    ) {
        parent::__construct();
    }

    /**
     * Run the scheduled billing cleanup pass.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max((int) $this->option('limit'), 1);
        $idempotencyRetentionDays = max((int) $this->option('idempotency-retention-days'), 1);
        $webhookRetentionDays = max((int) $this->option('webhook-response-retention-days'), 1);

        $idempotencyCutoff = now()->subDays($idempotencyRetentionDays);
        $webhookCutoff = now()->subDays($webhookRetentionDays);

        $idempotencyIds = IdempotencyKey::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $idempotencyCutoff)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $webhookIds = WebhookDelivery::query()
            ->whereIn('status', ['delivered', 'permanently_failed'])
            ->whereNotNull('response_body')
            ->where('updated_at', '<=', $webhookCutoff)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $idempotencyDeleted = 0;
        $webhookResponsesCleaned = 0;

        if (! $dryRun) {
            // WHY: Cleanup is limited to replay/runtime records. Financial
            // ledgers such as payments, invoices, wallet movements, and payment
            // transactions are never deleted by scheduler cleanup.
            $idempotencyDeleted = IdempotencyKey::query()
                ->whereIn('id', $idempotencyIds)
                ->delete();

            foreach ($webhookIds as $deliveryId) {
                $delivery = WebhookDelivery::query()->find($deliveryId);

                if (! $delivery) {
                    continue;
                }

                $delivery->forceFill([
                    'response_body' => null,
                    'metadata' => array_merge($delivery->metadata ?? [], [
                        'response_body_cleaned_at' => now()->toISOString(),
                    ]),
                ])->save();

                $webhookResponsesCleaned++;
            }
        }

        $this->recordActivity(
            $dryRun,
            $idempotencyIds->count(),
            $webhookIds->count(),
            $idempotencyDeleted,
            $webhookResponsesCleaned,
        );

        $this->renderSummary([
            'dry_run' => $dryRun ? 'yes' : 'no',
            'limit' => $limit,
            'idempotency_candidates' => $idempotencyIds->count(),
            'webhook_response_candidates' => $webhookIds->count(),
            'idempotency_deleted' => $idempotencyDeleted,
            'webhook_responses_cleaned' => $webhookResponsesCleaned,
            'financial_ledgers_deleted' => 0,
        ], 'Billing Cleanup');

        return self::SUCCESS;
    }

    private function recordActivity(
        bool $dryRun,
        int $idempotencyCandidates,
        int $webhookCandidates,
        int $idempotencyDeleted,
        int $webhookResponsesCleaned,
    ): void {
        $this->activityService->log(null, 'billing.scheduler.cleanup', 'Billing scheduler cleanup completed', [
            'source' => 'billing_scheduler',
            'module' => 'billing',
            'dry_run' => $dryRun,
            'idempotency_candidates' => $idempotencyCandidates,
            'webhook_response_candidates' => $webhookCandidates,
            'idempotency_deleted' => $idempotencyDeleted,
            'webhook_responses_cleaned' => $webhookResponsesCleaned,
            'financial_ledgers_deleted' => 0,
        ]);
    }
}
