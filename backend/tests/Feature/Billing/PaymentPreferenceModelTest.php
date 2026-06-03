<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\UserPaymentPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPreferenceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_preference_model_relations_and_casts_work(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->usd()->base()->create();
        $paymentMethod = PaymentMethod::factory()->fakeCard()->create([
            'user_id' => $user->id,
        ]);

        $preference = UserPaymentPreference::factory()
            ->autoChargeEnabled()
            ->autoTopUpEnabled($currency)
            ->create([
                'user_id' => $user->id,
                'default_payment_method_id' => $paymentMethod->id,
                'metadata' => ['safe' => true],
            ]);

        $this->assertTrue($user->paymentPreference->is($preference));
        $this->assertTrue($preference->user->is($user));
        $this->assertTrue($preference->defaultPaymentMethod->is($paymentMethod));
        $this->assertTrue($preference->autoTopUpCurrency->is($currency));
        $this->assertTrue($preference->auto_charge_enabled);
        $this->assertTrue($preference->auto_top_up_enabled);
        $this->assertSame(1000, $preference->auto_top_up_threshold_amount);
        $this->assertSame(5000, $preference->auto_top_up_amount);
        $this->assertSame(['safe' => true], $preference->metadata);
    }
}
