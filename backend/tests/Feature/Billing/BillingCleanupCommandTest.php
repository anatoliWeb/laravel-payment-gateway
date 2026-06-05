<?php

namespace Tests\Feature\Billing;

use App\Models\IdempotencyKey;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\WalletTransaction;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillingCleanupCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cleanup_dry_run_reports_without_deleting_financial_ledgers(): void
    {
        $payment = Payment::factory()->create(['subscription_id' => null]);
        $transaction = PaymentTransaction::factory()->create(['payment_id' => $payment->id]);
        $invoice = Invoice::factory()->create(['payment_id' => $payment->id, 'subscription_id' => null]);
        $walletTransaction = WalletTransaction::factory()->create(['payment_id' => $payment->id]);
        $idempotency = IdempotencyKey::factory()->create([
            'expires_at' => now()->subDays(10),
        ]);
        $delivery = WebhookDelivery::factory()->delivered()->create([
            'payment_id' => $payment->id,
            'subscription_id' => null,
            'response_body' => 'old response body',
            'updated_at' => now()->subDays(40),
        ]);

        $this->artisan('billing:cleanup', ['--dry-run' => true])
            ->expectsOutputToContain('Billing Cleanup')
            ->expectsOutputToContain('financial_ledgers_deleted')
            ->assertExitCode(0);

        $this->assertDatabaseHas('idempotency_keys', ['id' => $idempotency->id]);
        $this->assertSame('old response body', $delivery->fresh()->response_body);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('payment_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $walletTransaction->id]);
    }

    public function test_cleanup_respects_retention_policy_for_non_ledger_data(): void
    {
        $idempotency = IdempotencyKey::factory()->create([
            'expires_at' => now()->subDays(10),
        ]);
        $fresh = IdempotencyKey::factory()->create([
            'expires_at' => now()->addDay(),
        ]);
        $delivery = WebhookDelivery::factory()->delivered()->create([
            'response_body' => 'old response body',
            'updated_at' => now()->subDays(40),
        ]);

        $this->artisan('billing:cleanup')->assertExitCode(0);

        $this->assertDatabaseMissing('idempotency_keys', ['id' => $idempotency->id]);
        $this->assertDatabaseHas('idempotency_keys', ['id' => $fresh->id]);
        $this->assertNull($delivery->fresh()->response_body);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'billing.scheduler.cleanup',
        ]);
    }
}
