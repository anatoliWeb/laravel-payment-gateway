<?php

namespace Database\Factories;

use App\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IdempotencyKey>
 *
 * Generates idempotency persistence rows for tests without request-guard logic.
 */
class IdempotencyKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'idem_'.Str::lower(Str::random(24)),
            'method' => 'POST',
            'endpoint' => '/api/v1/billing/payments',
            'request_hash' => hash('sha256', Str::random(40)),
            'response_body' => [
                'success' => true,
                'source' => 'factory',
            ],
            'response_status' => 201,
            'related_type' => null,
            'related_id' => null,
            'status' => 'completed',
            'locked_until' => null,
            'expires_at' => now()->addHour(),
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'response_body' => null,
            'response_status' => null,
            'locked_until' => now()->addMinutes(5),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function conflict(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'conflict',
            'response_body' => [
                'success' => false,
                'code' => 'idempotency_conflict',
            ],
            'response_status' => 409,
        ]);
    }

    // WHY: Expired state is modeled as stored data only; cleanup and replay
    // enforcement belong to a dedicated service later.
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subMinute(),
        ]);
    }
}

