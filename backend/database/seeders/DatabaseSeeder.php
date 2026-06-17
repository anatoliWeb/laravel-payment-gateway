<?php

namespace Database\Seeders;

use Database\Seeders\settings\SettingsSeeder;
use Database\Seeders\Translations\TranslationsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\Billing\BillingDemoSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            BillingPermissionSeeder::class,
            ActivitySeeder::class,
            SettingsSeeder::class,
            TranslationsSeeder::class,
            CurrencySeeder::class,
            BillingSeeder::class,
            CompanySellerSeeder::class,
        ]);

        // WHY:
        // Demo billing data is local-only for screenshots and portfolio review.
        // It must never replace the baseline seeders.
        if ($this->shouldSeedBillingDemoData()) {
            $this->call(BillingDemoSeeder::class);
        }

        if (! app()->environment('production') && (bool) env('CHAT_DEMO_SEED', false)) {
            $this->call(ChatDemoSeeder::class);
        }
    }

    private function shouldSeedBillingDemoData(): bool
    {
        return app()->environment('local')
            && filter_var(env('BILLING_DEMO_SEED', false), FILTER_VALIDATE_BOOLEAN);
    }
}
