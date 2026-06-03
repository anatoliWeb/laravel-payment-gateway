<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_wallet_per_user(): void
    {
        $user = User::factory()->create();

        $first = app(WalletService::class)->getOrCreateWallet($user);
        $second = app(WalletService::class)->getOrCreateWallet($user);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Wallet::query()->where('user_id', $user->id)->count());
    }

    public function test_it_creates_one_balance_per_wallet_and_currency(): void
    {
        $user = User::factory()->create();
        Currency::factory()->usd()->base()->create();

        $first = app(WalletService::class)->getOrCreateBalance($user, 'usd');
        $second = app(WalletService::class)->getOrCreateBalance($user, 'USD');

        $this->assertNotNull($first);
        $this->assertTrue($first->is($second));
        $this->assertSame(1, WalletBalance::query()->where('wallet_id', $first->wallet_id)->count());
        $this->assertSame(0, $first->available_amount);
        $this->assertSame(0, $first->held_amount);
    }

    public function test_get_balance_returns_existing_balance_or_null_for_missing_currency(): void
    {
        $user = User::factory()->create();
        Currency::factory()->usd()->base()->create();
        Currency::factory()->uah()->inactive()->create();

        $balance = app(WalletService::class)->getOrCreateBalance($user, 'USD');

        $this->assertTrue(app(WalletService::class)->getBalance($user, 'USD')->is($balance));
        $this->assertNull(app(WalletService::class)->getBalance($user, 'EUR'));
        $this->assertNull(app(WalletService::class)->getOrCreateBalance($user, 'UAH'));
    }
}
