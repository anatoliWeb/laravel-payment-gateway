<?php

namespace Tests\Feature\Billing;

use Database\Seeders\BillingDemoSeeder;
use Database\Seeders\CompanySellerSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseSeederBaselineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_database_seeder_runs_baseline_seeders_without_demo_billing_data_in_testing_environment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@test.com']);
        $this->assertDatabaseHas('permissions', ['name' => 'billing.reports.view']);
        $this->assertDatabaseHas('plans', ['slug' => 'free']);
        $this->assertDatabaseHas('currencies', ['code' => 'USD']);
        $this->assertDatabaseHas('companies', ['slug' => CompanySellerSeeder::COMPANY_SLUG]);
        $this->assertDatabaseHas('sellers', ['slug' => CompanySellerSeeder::SELLER_SLUG]);
        $this->assertDatabaseMissing('users', ['email' => BillingDemoSeeder::ADMIN_EMAIL]);
        $this->assertDatabaseMissing('users', ['email' => BillingDemoSeeder::OPERATOR_EMAIL]);
    }

    public function test_database_seeder_appends_local_demo_billing_data_only_when_enabled(): void
    {
        $this->withEnvironment('local', function (): void {
            $this->withEnvVar('BILLING_DEMO_SEED', 'true', function (): void {
                $this->seed(DatabaseSeeder::class);

                $this->assertDatabaseHas('users', ['email' => 'admin@test.com']);
                $this->assertDatabaseHas('users', ['email' => BillingDemoSeeder::ADMIN_EMAIL]);
                $this->assertDatabaseHas('users', ['email' => BillingDemoSeeder::OPERATOR_EMAIL]);
            });
        });
    }

    private function withEnvironment(string $environment, callable $callback): void
    {
        $originalEnvironment = app()->environment();
        $this->app->detectEnvironment(fn () => $environment);

        try {
            $callback();
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    private function withEnvVar(string $key, ?string $value, callable $callback): void
    {
        $originalExists = array_key_exists($key, $_ENV)
            || array_key_exists($key, $_SERVER)
            || getenv($key) !== false;
        $originalValue = getenv($key);

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            $callback();
        } finally {
            if ($originalExists && $originalValue !== false) {
                putenv("{$key}={$originalValue}");
                $_ENV[$key] = $originalValue;
                $_SERVER[$key] = $originalValue;
            } else {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            }
        }
    }
}
