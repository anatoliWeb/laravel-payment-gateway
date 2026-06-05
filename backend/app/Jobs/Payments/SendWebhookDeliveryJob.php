<?php

namespace App\Jobs\Payments;

use App\Models\WebhookDelivery;
use App\Services\Payments\WebhookDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 20;

    public function __construct(
        public int $webhookDeliveryId,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(WebhookDeliveryService $deliveryService): void
    {
        $delivery = WebhookDelivery::query()->find($this->webhookDeliveryId);

        if (! $delivery || in_array($delivery->status, ['delivered', 'permanently_failed'], true)) {
            return;
        }

        if (! $deliveryService->shouldRetry($delivery)) {
            return;
        }

        $payload = (array) $delivery->payload;
        $headers = $deliveryService->headersFor($delivery->uuid, $delivery->event, $payload);
        $delivery->forceFill([
            'status' => 'processing',
            'attempts' => ((int) $delivery->attempts) + 1,
            'last_attempt_at' => now(),
            'metadata' => array_merge($delivery->metadata ?? [], [
                'headers' => $headers,
                'last_attempt_started_at' => now()->toISOString(),
            ]),
        ])->save();

        try {
            $response = Http::timeout((int) config('billing.webhooks.timeout_seconds', 5))
                ->withHeaders(array_merge([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'LaravelPaymentGatewayWebhook/1.0',
                ], $headers))
                ->post($delivery->url, $payload);

            if ($response->successful()) {
                $deliveryService->markDelivered($delivery->refresh(), $response->status(), $response->body());

                return;
            }

            $deliveryService->markFailed($delivery->refresh(), $response->status(), $response->body());
        } catch (Throwable $exception) {
            Log::warning('billing.webhook_delivery_exception', [
                'delivery_id' => $delivery->id,
                'delivery_uuid' => $delivery->uuid,
                'event_type' => $delivery->event,
                'error' => $exception->getMessage(),
            ]);

            $deliveryService->markFailed($delivery->refresh(), null, 'Webhook delivery exception');
        }
    }
}
