<?php

namespace Tests\Feature\Billing;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillingSubscriptionExpirationCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_elapsed_subscription_is_marked_expired_without_renewal_or_charge(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => 'active',
            'current_period_end' => now()->subMinute(),
        ]);
        $future = Subscription::factory()->create([
            'status' => 'active',
            'current_period_end' => now()->addDay(),
        ]);

        $this->artisan('billing:check-subscription-expiration')
            ->expectsOutputToContain('Billing Subscription Expiration Check')
            ->assertExitCode(0);

        $this->assertSame('expired', $subscription->fresh()->status);
        $this->assertSame('active', $future->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'billing.scheduler.subscription_expiration_check',
        ]);
    }
}
