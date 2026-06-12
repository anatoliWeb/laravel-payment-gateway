<?php

namespace Database\Seeders;

use Database\Seeders\settings\SettingsSeeder;
use Database\Seeders\Translations\TranslationsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        // Demo billing data is useful for portfolio walkthroughs, but it must
        // stay opt-in so the base seeder remains safe for normal environments.
        if (! app()->environment('production') && (bool) env('BILLING_DEMO_SEED', false)) {
            $this->call(BillingDemoSeeder::class);
        }

        if (! app()->environment('production') && (bool) env('CHAT_DEMO_SEED', false)) {
            $this->call(ChatDemoSeeder::class);
        }
    }
}
