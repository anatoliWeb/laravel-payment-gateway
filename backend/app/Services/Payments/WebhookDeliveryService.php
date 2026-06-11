<?php

namespace App\Services\Payments;

use App\Jobs\Payments\SendWebhookDeliveryJob;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\ActivityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WebhookDeliveryService
{
    private const SUPPORTED_EVENTS = [
        'payment.created',
        'payment.succeeded',
        'payment.failed',
        'payment.expired',
        'payment.cancelled',
    ];

    private const RETRYABLE_STATUSES = ['failed', 'retrying', 'permanently_failed'];

    private const FORBIDDEN_KEYS = [
        'card_number',
        'number',
        'pan',
        'cvv',
        'cvc',
        'security_code',
        'token',
        'secret',
        'password',
        'private_key',
        'provider_config',
        'credentials',
        'idempotency_key',
        'raw_idempotency_key',
    ];

    public function __construct(
        private readonly ActivityService $activityService,
    ) {}

    public function createForPaymentEvent(Payment $payment, string $eventType, array $metadata = []): ?WebhookDelivery
    {
        if (! in_array($eventType, self::SUPPORTED_EVENTS, true)) {
            throw new RuntimeException('webhook_event_not_supported');
        }

        if (blank($payment->callback_url)) {
            return null;
        }

        $uuid = (string) Str::uuid();
        $occurredAt = now();
        $payload = $this->buildPayload($payment, $eventType, $uuid, $occurredAt, $metadata);
        $headers = $this->headersFor($uuid, $eventType, $payload, $occurredAt);

        $delivery = WebhookDelivery::query()->create([
            'uuid' => $uuid,
            'payment_id' => $payment->id,
            'subscription_id' => $payment->subscription_id,
            'invoice_id' => $payment->invoice_id,
            'event' => $eventType,
            'url' => $payment->callback_url,
            'status' => 'pending',
            'payload' => $payload,
            'response_status' => null,
            'response_body' => null,
            'attempts' => 0,
            'max_attempts' => $this->maxAttempts(),
            'next_retry_at' => now(),
            'last_attempt_at' => null,
            'delivered_at' => null,
            'failed_at' => null,
            'metadata' => [
                'source' => 'webhook_delivery_service',
                'headers' => $headers,
                'callback_host' => parse_url((string) $payment->callback_url, PHP_URL_HOST),
                'company_id' => $payment->company_id,
                'seller_id' => $payment->seller_id,
            ],
        ]);

        $this->recordActivity(null, 'billing.webhook_delivery_created', $delivery);

        return $delivery;
    }

    public function dispatch(WebhookDelivery $delivery): void
    {
        $this->recordActivity(null, 'billing.webhook_dispatched', $delivery);

        SendWebhookDeliveryJob::dispatch($delivery->id)->afterCommit();
    }

    public function markDelivered(WebhookDelivery $delivery, int $statusCode, ?string $responseBody = null): void
    {
        $delivery->forceFill([
            'status' => 'delivered',
            'response_status' => $statusCode,
            'response_body' => $this->sanitizeResponseBody($responseBody),
            'next_retry_at' => null,
            'delivered_at' => now(),
            'failed_at' => null,
        ])->save();

        $this->recordActivity(null, 'billing.webhook_delivered', $delivery);
    }

    public function markFailed(WebhookDelivery $delivery, ?int $statusCode, ?string $errorMessage = null): void
    {
        $attempts = (int) $delivery->attempts;
        $permanent = $attempts >= (int) $delivery->max_attempts;

        $delivery->forceFill([
            'status' => $permanent ? 'permanently_failed' : 'retrying',
            'response_status' => $statusCode,
            'response_body' => $this->sanitizeResponseBody($errorMessage),
            'next_retry_at' => $permanent ? null : now()->addSeconds($this->backoffSeconds($attempts)),
            'failed_at' => now(),
        ])->save();

        $this->recordActivity(
            null,
            $permanent ? 'billing.webhook_permanently_failed' : 'billing.webhook_failed',
            $delivery,
        );
    }

    public function shouldRetry(WebhookDelivery $delivery): bool
    {
        return in_array($delivery->status, ['pending', 'queued', 'failed', 'retrying'], true)
            && (int) $delivery->attempts < (int) $delivery->max_attempts;
    }

    public function retry(WebhookDelivery $delivery, User $actor): WebhookDelivery
    {
        if (! in_array($delivery->status, self::RETRYABLE_STATUSES, true)) {
            throw new RuntimeException('webhook_retry_not_allowed');
        }

        return DB::transaction(function () use ($delivery, $actor): WebhookDelivery {
            $locked = WebhookDelivery::query()
                ->whereKey($delivery->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, self::RETRYABLE_STATUSES, true)) {
                throw new RuntimeException('webhook_retry_not_allowed');
            }

            $locked->forceFill([
                'status' => 'pending',
                'response_status' => null,
                'next_retry_at' => now(),
                'delivered_at' => null,
                'failed_at' => null,
                'metadata' => array_merge($locked->metadata ?? [], [
                    'manual_retry_requested_by' => $actor->id,
                    'manual_retry_requested_at' => now()->toISOString(),
                ]),
            ])->save();

            $this->recordActivity($actor->id, 'billing.webhook_retry_requested', $locked);
            $this->dispatch($locked);

            return $locked->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function headersFor(string $deliveryUuid, string $eventType, array $payload, ?Carbon $timestamp = null): array
    {
        $timestamp ??= now();
        $headers = (array) config('billing.webhooks.headers', []);

        return [
            $headers['event'] ?? 'X-Billing-Event' => $eventType,
            $headers['delivery'] ?? 'X-Billing-Delivery' => $deliveryUuid,
            $headers['signature'] ?? 'X-Billing-Signature' => $this->signature($payload),
            $headers['timestamp'] ?? 'X-Billing-Timestamp' => (string) $timestamp->getTimestamp(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(
        Payment $payment,
        string $eventType,
        string $eventId,
        Carbon $occurredAt,
        array $metadata,
    ): array {
        return [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt->toISOString(),
            'payment' => array_filter([
                'id' => $payment->id,
                'uuid' => $payment->uuid,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider' => $payment->provider,
                'provider_reference' => $payment->provider_reference,
                'payer_user_id' => $payment->payer_user_id,
                'company_id' => $payment->company_id,
                'seller_id' => $payment->seller_id,
                'metadata' => $this->safePaymentMetadata((array) $payment->metadata),
            ], fn ($value) => $value !== null && $value !== []),
            'metadata' => $this->sanitizeMetadata($metadata),
        ];
    }

    private function signature(array $payload): string
    {
        return hash_hmac('sha256', $this->jsonPayload($payload), $this->secret());
    }

    private function jsonPayload(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function secret(): string
    {
        $secret = (string) config('billing.webhooks.secret', '');

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $secret;
    }

    /**
     * @return array<string, mixed>
     */
    private function safePaymentMetadata(array $metadata): array
    {
        unset($metadata['idempotency_key_hash']);

        return $this->sanitizeMetadata($metadata);
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN_KEYS, true)) {
                unset($metadata[$key]);

                continue;
            }

            if (is_array($value)) {
                $metadata[$key] = $this->sanitizeMetadata($value);
            }
        }

        return $metadata;
    }

    private function sanitizeResponseBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $body = preg_replace('/(secret|token|password|signature)=([^&\s]+)/i', '$1=[filtered]', $body) ?? $body;

        return mb_substr($body, 0, (int) config('billing.webhooks.response_body_limit', 2000));
    }

    private function maxAttempts(): int
    {
        return max((int) config('billing.webhooks.max_attempts', 5), 1);
    }

    private function backoffSeconds(int $attempts): int
    {
        return min(300, 2 ** max($attempts - 1, 0) * 30);
    }

    private function recordActivity(?int $actorId, string $action, WebhookDelivery $delivery): void
    {
        try {
            $this->activityService->log($actorId, $action, 'Billing webhook delivery event', [
                'source' => 'webhook_delivery_service',
                'module' => 'billing',
                'webhook_delivery_id' => $delivery->id,
                'webhook_delivery_uuid' => $delivery->uuid,
                'payment_id' => $delivery->payment_id,
                'event_type' => $delivery->event,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'callback_host' => parse_url((string) $delivery->url, PHP_URL_HOST),
                'company_id' => $delivery->payment?->company_id,
                'seller_id' => $delivery->payment?->seller_id,
            ]);
        } catch (Throwable) {
            // Webhook activity logs must not break payment or retry flows.
        }
    }
}
