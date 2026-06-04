<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\SellerCustomer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerCustomer>
 */
class SellerCustomerFactory extends Factory
{
    protected $model = SellerCustomer::class;

    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'user_id' => User::factory(),
            'status' => 'active',
            'metadata' => ['source' => 'factory'],
        ];
    }
}
