<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookDelivery>
 *
 * Generates webhook delivery rows for tests without sending HTTP callbacks.
 */
class WebhookDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'payment_id' => Payment::factory(),
            'subscription_id' => function (array $attributes) {
                $payment = Payment::query()->find($attributes['payment_id']);

                return $payment?->subscription_id
                    ?? Subscription::factory()->create()->id;
            },
            'invoice_id' => null,
            'event' => 'payment.created',
            'url' => 'https://example.test/webhooks/billing',
            'status' => 'pending',
            'payload' => [
                'source' => 'factory',
            ],
            'response_status' => null,
            'response_body' => null,
            'attempts' => 0,
            'max_attempts' => 3,
            'next_retry_at' => null,
            'last_attempt_at' => null,
            'delivered_at' => null,
            'failed_at' => null,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'response_status' => 200,
            'response_body' => 'ok',
            'attempts' => 1,
            'last_attempt_at' => now(),
            'delivered_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'response_status' => 500,
            'response_body' => 'failed',
            'attempts' => 1,
            'next_retry_at' => now()->addMinutes(5),
            'last_attempt_at' => now(),
            'failed_at' => now(),
        ]);
    }

    // WHY: Factory states model delivery persistence only and never trigger
    // queue jobs, retries, or external HTTP behavior.
    public function permanentlyFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'permanently_failed',
            'response_status' => 500,
            'response_body' => 'permanent_failure',
            'attempts' => 3,
            'last_attempt_at' => now(),
            'failed_at' => now(),
            'next_retry_at' => null,
        ]);
    }
}
