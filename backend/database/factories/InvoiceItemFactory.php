<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 *
 * Generates integer-only invoice line item amounts for tests.
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'item_type' => 'simple',
            'description' => fake()->sentence(4),
            'quantity' => 1,
            'unit_amount' => 2900,
            'subtotal_amount' => 2900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 2900,
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function simple(): static
    {
        return $this->state(fn () => ['item_type' => 'simple']);
    }

    public function subscriptionPlan(): static
    {
        return $this->state(fn () => [
            'item_type' => 'subscription_plan',
            'description' => 'Subscription plan charge',
        ]);
    }

    public function usageCharge(): static
    {
        return $this->state(fn () => [
            'item_type' => 'usage_charge',
            'description' => 'Usage charge',
        ]);
    }

    public function discount(): static
    {
        return $this->state(fn () => [
            'item_type' => 'discount',
            'description' => 'Invoice discount',
            'unit_amount' => 0,
            'subtotal_amount' => 0,
            'discount_amount' => 500,
            'total_amount' => 0,
        ]);
    }

    public function tax(): static
    {
        return $this->state(fn () => [
            'item_type' => 'tax',
            'description' => 'Invoice tax',
            'tax_amount' => 200,
            'total_amount' => 3100,
        ]);
    }
}
