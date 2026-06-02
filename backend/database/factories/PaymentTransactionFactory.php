<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTransaction>
 *
 * Generates append-only payment timeline rows for tests.
 */
class PaymentTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'type' => 'payment_created',
            'status_from' => null,
            'status_to' => 'pending',
            'amount' => 2900,
            'currency' => 'USD',
            'message' => 'Factory payment timeline event',
            'payload' => [
                'source' => 'factory',
            ],
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'payment_succeeded',
            'status_from' => 'processing',
            'status_to' => 'succeeded',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'payment_failed',
            'status_from' => 'processing',
            'status_to' => 'failed',
        ]);
    }

    // WHY: Timeline factories stay persistence-only and never emulate webhook
    // jobs or payment state orchestration.
    public function webhookQueued(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'webhook_queued',
            'status_from' => null,
            'status_to' => null,
        ]);
    }
}

