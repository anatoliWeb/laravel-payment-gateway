<?php

namespace Tests\Feature\Billing;

use App\Models\FeatureUsage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillingResetUsageCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_due_daily_monthly_and_billing_cycle_usage_is_reset(): void
    {
        $daily = FeatureUsage::factory()->create([
            'feature_key' => 'chat.messages.daily',
            'period' => 'daily',
            'used' => 10,
            'reset_at' => now()->subMinute(),
        ]);
        $monthly = FeatureUsage::factory()->create([
            'feature_key' => 'chat.messages.monthly',
            'period' => 'monthly',
            'used' => 20,
            'reset_at' => now()->subMinute(),
        ]);
        $billingCycle = FeatureUsage::factory()->create([
            'feature_key' => 'dialer.calls.monthly',
            'period' => 'billing_cycle',
            'used' => 30,
            'reset_at' => now()->subMinute(),
        ]);
        $lifetime = FeatureUsage::factory()->create([
            'feature_key' => 'platform.api_tokens.count',
            'period' => 'lifetime',
            'used' => 40,
            'reset_at' => now()->subMinute(),
        ]);

        $this->artisan('billing:reset-usage')
            ->expectsOutputToContain('Billing Reset Usage')
            ->assertExitCode(0);

        $this->assertSame(0, $daily->fresh()->used);
        $this->assertSame(0, $monthly->fresh()->used);
        $this->assertSame(0, $billingCycle->fresh()->used);
        $this->assertSame(40, $lifetime->fresh()->used);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'billing.scheduler.reset_usage',
        ]);
    }

    public function test_usage_reset_is_idempotent(): void
    {
        $usage = FeatureUsage::factory()->create([
            'feature_key' => 'chat.attachments.monthly',
            'period' => 'monthly',
            'used' => 15,
            'reset_at' => now()->subMinute(),
        ]);

        $this->artisan('billing:reset-usage')->assertExitCode(0);
        $this->artisan('billing:reset-usage')->assertExitCode(0);

        $this->assertSame(0, $usage->fresh()->used);
    }
}
