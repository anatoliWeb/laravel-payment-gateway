<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use App\Services\Billing\WalletTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WalletTransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_increases_available_balance_and_writes_ledger(): void
    {
        $user = $this->userWithCurrency('USD');

        $transaction = app(WalletTransactionService::class)->credit($user, 'USD', 2500, idempotencyKey: 'credit-1');
        $balance = app(WalletService::class)->getBalance($user, 'USD');

        $this->assertSame(2500, $balance->available_amount);
        $this->assertSame(0, $balance->held_amount);
        $this->assertSame('top_up', $transaction->type);
        $this->assertSame('credit', $transaction->direction);
        $this->assertSame(0, $transaction->balance_available_before);
        $this->assertSame(2500, $transaction->balance_available_after);
    }

    public function test_debit_decreases_available_balance_and_blocks_insufficient_funds(): void
    {
        $user = $this->userWithCurrency('USD');
        app(WalletTransactionService::class)->credit($user, 'USD', 3000);

        $debit = app(WalletTransactionService::class)->debit($user, 'USD', 1200);
        $balance = app(WalletService::class)->getBalance($user, 'USD');

        $this->assertSame(1800, $balance->available_amount);
        $this->assertSame('debit', $debit->type);
        $this->assertSame(3000, $debit->balance_available_before);
        $this->assertSame(1800, $debit->balance_available_after);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('insufficient_wallet_balance');

        app(WalletTransactionService::class)->debit($user, 'USD', 5000);
    }

    public function test_hold_and_release_move_amount_between_available_and_held(): void
    {
        $user = $this->userWithCurrency('USD');
        app(WalletTransactionService::class)->credit($user, 'USD', 5000);

        $hold = app(WalletTransactionService::class)->hold($user, 'USD', 2000);
        $held = app(WalletService::class)->getBalance($user, 'USD');

        $this->assertSame(3000, $held->available_amount);
        $this->assertSame(2000, $held->held_amount);
        $this->assertSame('hold', $hold->type);
        $this->assertSame(5000, $hold->balance_available_before);
        $this->assertSame(3000, $hold->balance_available_after);
        $this->assertSame(0, $hold->balance_held_before);
        $this->assertSame(2000, $hold->balance_held_after);

        $release = app(WalletTransactionService::class)->release($user, 'USD', 1500);
        $released = app(WalletService::class)->getBalance($user, 'USD');

        $this->assertSame(4500, $released->available_amount);
        $this->assertSame(500, $released->held_amount);
        $this->assertSame('release', $release->type);
    }

    public function test_refund_increases_available_balance(): void
    {
        $user = $this->userWithCurrency('USD');

        $refund = app(WalletTransactionService::class)->refund($user, 'USD', 700);
        $balance = app(WalletService::class)->getBalance($user, 'USD');

        $this->assertSame(700, $balance->available_amount);
        $this->assertSame('refund', $refund->type);
        $this->assertSame('credit', $refund->direction);
    }

    public function test_idempotency_key_prevents_duplicate_credit_and_debit(): void
    {
        $user = $this->userWithCurrency('USD');

        $firstCredit = app(WalletTransactionService::class)->credit($user, 'USD', 1000, idempotencyKey: 'same-credit');
        $secondCredit = app(WalletTransactionService::class)->credit($user, 'USD', 1000, idempotencyKey: 'same-credit');
        $balanceAfterCredit = app(WalletService::class)->getBalance($user, 'USD');

        $this->assertTrue($firstCredit->is($secondCredit));
        $this->assertSame(1000, $balanceAfterCredit->available_amount);

        $firstDebit = app(WalletTransactionService::class)->debit($user, 'USD', 400, idempotencyKey: 'same-debit');
        $secondDebit = app(WalletTransactionService::class)->debit($user, 'USD', 400, idempotencyKey: 'same-debit');
        $balanceAfterDebit = app(WalletService::class)->getBalance($user, 'USD');

        $this->assertTrue($firstDebit->is($secondDebit));
        $this->assertSame(600, $balanceAfterDebit->available_amount);
    }

    public function test_multi_currency_balances_are_isolated(): void
    {
        $user = $this->userWithCurrency('USD');
        Currency::factory()->eur()->create();

        app(WalletTransactionService::class)->credit($user, 'USD', 1000);
        app(WalletTransactionService::class)->credit($user, 'EUR', 2000);
        app(WalletTransactionService::class)->debit($user, 'USD', 300);

        $usd = app(WalletService::class)->getBalance($user, 'USD');
        $eur = app(WalletService::class)->getBalance($user, 'EUR');

        $this->assertSame(700, $usd->available_amount);
        $this->assertSame(2000, $eur->available_amount);
    }

    public function test_manual_adjustment_requires_audit_context_and_is_idempotent(): void
    {
        $target = $this->userWithCurrency('USD');
        $actor = User::factory()->create();

        $first = app(WalletTransactionService::class)->manualCredit(
            targetUser: $target,
            currencyCode: 'USD',
            amount: 1500,
            actor: $actor,
            reason: 'Support-approved correction',
            reference: 'ticket-2001',
            idempotencyKey: 'manual-credit-service-1',
            metadata: ['secret' => 'must-not-be-stored', 'case_type' => 'support'],
        );
        $second = app(WalletTransactionService::class)->manualCredit(
            targetUser: $target,
            currencyCode: 'USD',
            amount: 1500,
            actor: $actor,
            reason: 'Support-approved correction',
            reference: 'ticket-2001',
            idempotencyKey: 'manual-credit-service-1',
            metadata: ['case_type' => 'support'],
        );

        $this->assertTrue($first->is($second));
        $this->assertSame('adjustment', $first->type);
        $this->assertSame('credit', $first->direction);
        $this->assertSame(0, $first->balance_available_before);
        $this->assertSame(1500, $first->balance_available_after);
        $this->assertSame($actor->id, $first->metadata['actor_id']);
        $this->assertArrayNotHasKey('secret', $first->metadata);
        $this->assertSame(1, WalletTransaction::query()->where('type', 'adjustment')->count());
        $this->assertSame(1500, app(WalletService::class)->getBalance($target, 'USD')->available_amount);
    }

    public function test_manual_debit_blocks_insufficient_balance_and_idempotency_conflicts(): void
    {
        $target = $this->userWithCurrency('USD');
        $actor = User::factory()->create();
        $service = app(WalletTransactionService::class);
        $service->credit($target, 'USD', 2000);

        $service->manualDebit(
            targetUser: $target,
            currencyCode: 'USD',
            amount: 500,
            actor: $actor,
            reason: 'Reverse duplicated credit',
            idempotencyKey: 'manual-debit-service-1',
        );

        try {
            $service->manualDebit(
                targetUser: $target,
                currencyCode: 'USD',
                amount: 600,
                actor: $actor,
                reason: 'Conflicting retry',
                idempotencyKey: 'manual-debit-service-1',
            );

            $this->fail('Expected an idempotency key conflict.');
        } catch (RuntimeException $exception) {
            $this->assertSame('idempotency_key_conflict', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('insufficient_wallet_balance');

        $service->manualDebit(
            targetUser: $target,
            currencyCode: 'USD',
            amount: 5000,
            actor: $actor,
            reason: 'Excessive debit attempt',
            idempotencyKey: 'manual-debit-service-2',
        );
    }

    private function userWithCurrency(string $currencyCode): User
    {
        $user = User::factory()->create();

        Currency::factory()->create([
            'code' => $currencyCode,
            'name' => $currencyCode.' Currency',
            'symbol' => $currencyCode,
            'decimal_precision' => 2,
            'is_active' => true,
            'is_base' => $currencyCode === 'USD',
        ]);

        return $user;
    }
}
