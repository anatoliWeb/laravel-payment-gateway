<?php

namespace Tests\Feature\Billing;

use App\Events\Billing\PaymentExpired;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BillingExpirePendingPaymentsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_expired_pending_payment_becomes_expired_and_writes_timeline(): void
    {
        Event::fake([PaymentExpired::class]);

        $payment = Payment::factory()->pending()->create([
            'subscription_id' => null,
            'provider' => 'simulator',
            'created_at' => now()->subMinutes(45),
        ]);
        $final = Payment::factory()->succeeded()->create([
            'subscription_id' => null,
            'created_at' => now()->subMinutes(45),
        ]);

        $this->artisan('billing:expire-pending-payments', ['--ttl-minutes' => 30])
            ->expectsOutputToContain('Billing Expire Pending Payments')
            ->expectsOutputToContain('processed')
            ->assertExitCode(0);

        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->expired_at);
        $this->assertSame('succeeded', $final->fresh()->status);
        $this->assertSame(1, PaymentTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('type', 'payment_expired')
            ->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'billing.scheduler.expire_pending_payments',
        ]);

        Event::assertDispatched(PaymentExpired::class, fn (PaymentExpired $event): bool => $event->payment->is($payment));
    }

    public function test_repeated_expiration_command_is_idempotent(): void
    {
        $payment = Payment::factory()->processing()->create([
            'subscription_id' => null,
            'provider' => 'internal_wallet',
            'created_at' => now()->subMinutes(60),
        ]);

        $this->artisan('billing:expire-pending-payments', ['--ttl-minutes' => 30])->assertExitCode(0);
        $this->artisan('billing:expire-pending-payments', ['--ttl-minutes' => 30])->assertExitCode(0);

        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame(1, PaymentTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('type', 'payment_expired')
            ->count());
    }
}
