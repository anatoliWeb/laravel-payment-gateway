<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    protected $model = Seller::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'company_id' => Company::factory(),
            'owner_user_id' => User::factory(),
            'name' => fake()->company().' Merchant',
            'slug' => fake()->unique()->slug(2),
            'status' => 'active',
            'metadata' => ['source' => 'factory'],
        ];
    }
}
