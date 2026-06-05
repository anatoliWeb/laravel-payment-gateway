<?php

namespace Tests\Feature\Billing;

use App\Events\Billing\WalletCredited;
use App\Events\Billing\WalletDebited;
use App\Models\Currency;
use App\Models\User;
use App\Services\Billing\WalletTransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BillingWalletEventsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_wallet_credit_and_debit_dispatch_events(): void
    {
        Event::fake([WalletCredited::class, WalletDebited::class]);
        $user = $this->userWithCurrency('USD');
        $service = app(WalletTransactionService::class);

        $service->credit($user, 'USD', 2000, idempotencyKey: 'wallet-event-credit');
        $service->debit($user, 'USD', 500, idempotencyKey: 'wallet-event-debit');

        Event::assertDispatchedTimes(WalletCredited::class, 1);
        Event::assertDispatchedTimes(WalletDebited::class, 1);
    }

    public function test_wallet_adjustment_replay_does_not_duplicate_wallet_event(): void
    {
        Event::fake([WalletCredited::class]);
        $target = $this->userWithCurrency('USD');
        $actor = User::factory()->create();
        $service = app(WalletTransactionService::class);

        $service->manualCredit($target, 'USD', 1500, $actor, 'Support correction', idempotencyKey: 'wallet-adjustment-event');
        $service->manualCredit($target, 'USD', 1500, $actor, 'Support correction', idempotencyKey: 'wallet-adjustment-event');

        Event::assertDispatchedTimes(WalletCredited::class, 1);
    }

    private function userWithCurrency(string $currencyCode): User
    {
        $user = User::factory()->create();
        Currency::factory()->create([
            'code' => $currencyCode,
            'name' => "{$currencyCode} Currency",
            'symbol' => $currencyCode,
            'is_active' => true,
            'is_base' => $currencyCode === 'USD',
        ]);

        return $user;
    }
}
