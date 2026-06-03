<?php

namespace Tests\Feature\Billing;

use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentPreferencesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_preferences_creates_default_row(): void
    {
        $this->actingUser();

        $this->getJson('/api/v1/billing/payment-preferences')
            ->assertOk()
            ->assertJsonPath('data.strategy', 'wallet_first')
            ->assertJsonPath('data.auto_charge_enabled', false);
    }

    public function test_update_strategy_and_default_payment_method(): void
    {
        $user = $this->actingUser();
        $method = PaymentMethod::factory()->fakeCard()->create(['user_id' => $user->id]);

        $this->patchJson('/api/v1/billing/payment-preferences', [
            'strategy' => 'payment_method_only',
            'default_payment_method_id' => $method->id,
        ])->assertOk()
            ->assertJsonPath('data.strategy', 'payment_method_only')
            ->assertJsonPath('data.default_payment_method.id', $method->id);

        $this->assertTrue($method->refresh()->is_default);
    }

    public function test_enabling_auto_charge_tracks_consent_activity(): void
    {
        $user = $this->actingUser();

        $this->patchJson('/api/v1/billing/payment-preferences', [
            'auto_charge_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.auto_charge_enabled', true);

        $this->assertNotNull($user->refresh()->paymentPreference->auto_charge_consent_at);
        $this->assertSame(1, ActivityLog::query()
            ->where('user_id', $user->id)
            ->where('action', 'billing.auto_charge_consent_changed')
            ->count());
    }

    public function test_enabling_auto_top_up_validates_currency_and_amounts_without_triggering_payment(): void
    {
        $this->actingUser();

        $this->patchJson('/api/v1/billing/payment-preferences', [
            'auto_top_up_enabled' => true,
            'auto_top_up_threshold_amount' => 500,
            'auto_top_up_amount' => 2500,
            'auto_top_up_currency' => 'USD',
        ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'auto_top_up_currency_not_available');

        $this->activeCurrency('USD');

        $this->patchJson('/api/v1/billing/payment-preferences', [
            'auto_top_up_enabled' => true,
            'auto_top_up_threshold_amount' => 500,
            'auto_top_up_amount' => 2500,
            'auto_top_up_currency' => 'USD',
            'max_auto_top_up_per_day' => 2,
            'max_auto_top_up_per_month' => 5,
        ])->assertOk()
            ->assertJsonPath('data.auto_top_up_enabled', true)
            ->assertJsonPath('data.auto_top_up_currency.code', 'USD');

        $this->assertSame(0, Payment::query()->count());
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function activeCurrency(string $code): Currency
    {
        return Currency::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => "{$code} Currency",
                'symbol' => $code,
                'decimal_precision' => 2,
                'is_active' => true,
                'is_base' => $code === 'USD',
                'description' => 'Test currency.',
                'metadata' => ['source' => 'test'],
            ],
        );
    }
}
