<?php

namespace Database\Factories;

use App\Models\PaymentProviderAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentProviderAccount>
 */
class PaymentProviderAccountFactory extends Factory
{
    protected $model = PaymentProviderAccount::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'provider' => 'simulator',
            'display_name' => 'Simulator account',
            'status' => 'active',
            'mode' => 'test',
            'config_source' => 'database',
            'public_config' => ['simulator_safe' => true],
            'capabilities' => ['charge' => true],
            'last_verified_at' => now(),
            'metadata' => ['source' => 'factory'],
        ];
    }
}
