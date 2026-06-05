<?php

namespace App\Console\Commands\Billing;

use App\Console\Commands\BaseCommand;
use App\Models\WebhookDelivery;
use App\Services\ActivityService;
use App\Services\Payments\WebhookDeliveryService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dispatches due billing webhook deliveries back to the queue.
 */
class BillingRetryWebhooksCommand extends BaseCommand
{
    protected $signature = 'billing:retry-webhooks {--limit=100}';

    protected $description = 'Queue due pending, failed, and retrying billing webhooks.';

    private const DUE_STATUSES = ['pending', 'failed', 'retrying'];

    public function __construct(
        private readonly WebhookDeliveryService $webhookDeliveryService,
        private readonly ActivityService $activityService,
    ) {
        parent::__construct();
    }

    /**
     * Run the scheduled webhook retry pass.
     */
    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $ids = WebhookDelivery::query()
            ->whereIn('status', self::DUE_STATUSES)
            ->whereColumn('attempts', '<', 'max_attempts')
            ->where(function ($query): void {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $deliveryId) {
            try {
                $delivery = DB::transaction(function () use ($deliveryId): ?WebhookDelivery {
                    $delivery = WebhookDelivery::query()
                        ->whereKey($deliveryId)
                        ->lockForUpdate()
                        ->first();

                    if (! $delivery || ! $this->isStillDue($delivery)) {
                        return null;
                    }

                    // WHY: Marking as queued before dispatch prevents overlapping
                    // scheduler ticks from enqueueing the same callback repeatedly.
                    $delivery->forceFill([
                        'status' => 'queued',
                        'metadata' => array_merge($delivery->metadata ?? [], [
                            'queued_by' => 'billing_scheduler',
                            'queued_at' => now()->toISOString(),
                        ]),
                    ])->save();

                    return $delivery->refresh();
                });

                if (! $delivery) {
                    $skipped++;
                    continue;
                }

                $this->webhookDeliveryService->dispatch($delivery);
                $this->recordActivity($delivery);
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
        ], 'Billing Retry Webhooks');

        return self::SUCCESS;
    }

    private function isStillDue(WebhookDelivery $delivery): bool
    {
        return in_array($delivery->status, self::DUE_STATUSES, true)
            && (int) $delivery->attempts < (int) $delivery->max_attempts
            && ($delivery->next_retry_at === null || $delivery->next_retry_at->lessThanOrEqualTo(now()));
    }

    private function recordActivity(WebhookDelivery $delivery): void
    {
        $this->activityService->log(null, 'billing.scheduler.retry_webhooks', 'Billing scheduler queued webhook retry', [
            'source' => 'billing_scheduler',
            'module' => 'billing',
            'webhook_delivery_id' => $delivery->id,
            'webhook_delivery_uuid' => $delivery->uuid,
            'payment_id' => $delivery->payment_id,
            'event_type' => $delivery->event,
            'status' => $delivery->status,
            'attempts' => $delivery->attempts,
            'max_attempts' => $delivery->max_attempts,
        ]);
    }
}
