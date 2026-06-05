<?php

namespace Tests\Feature\Billing;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillingSchedulerRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_billing_scheduler_commands_are_registered(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('billing:expire-pending-payments')
            ->expectsOutputToContain('billing:reset-usage')
            ->expectsOutputToContain('billing:check-subscription-expiration')
            ->expectsOutputToContain('billing:retry-webhooks')
            ->expectsOutputToContain('billing:cleanup')
            ->assertExitCode(0);
    }
}
