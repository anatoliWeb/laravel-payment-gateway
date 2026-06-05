<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 *
 * Generates invoice rows for lifecycle tests without payment provider side effects.
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'number' => null,
            'user_id' => User::factory(),
            'payer_user_id' => fn (array $attributes) => $attributes['user_id'],
            'company_id' => null,
            'seller_id' => null,
            'subscription_id' => null,
            'payment_id' => null,
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'USD',
            'subtotal_amount' => 2900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 2900,
            'paid_amount' => 0,
            'due_amount' => 2900,
            'issued_at' => null,
            'due_at' => now()->addDays(14),
            'paid_at' => null,
            'voided_at' => null,
            'overdue_at' => null,
            'description' => 'Factory invoice',
            'metadata' => ['source' => 'factory'],
            'ownership_metadata' => ['scope' => 'user'],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_DRAFT]);
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_ISSUED,
            'number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'issued_at' => now(),
        ]);
    }

    public function paymentPending(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_PAYMENT_PENDING]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_PAID,
            'paid_amount' => $attributes['total_amount'] ?? 2900,
            'due_amount' => 0,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_FAILED]);
    }

    public function void(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_VOID,
            'voided_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_OVERDUE,
            'overdue_at' => now(),
        ]);
    }

    public function forCompany(): static
    {
        return $this->state(fn () => [
            'company_id' => Company::factory(),
            'ownership_metadata' => ['scope' => 'company'],
        ]);
    }

    public function forSeller(): static
    {
        return $this->state(function () {
            $seller = Seller::factory()->create();

            return [
                'company_id' => $seller->company_id,
                'seller_id' => $seller->id,
                'ownership_metadata' => ['scope' => 'seller'],
            ];
        });
    }

    public function withSubscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_id' => Subscription::factory()->create([
                'user_id' => $attributes['payer_user_id'] ?? $attributes['user_id'],
            ])->id,
        ]);
    }

    public function withPayment(): static
    {
        return $this->state(fn () => [
            'payment_id' => Payment::factory(),
        ]);
    }
}
