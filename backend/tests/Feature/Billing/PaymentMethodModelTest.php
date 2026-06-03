<?php

namespace Tests\Feature\Billing;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentMethodModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_method_model_relations_and_casts_work(): void
    {
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->fakeCard()->default()->create([
            'user_id' => $user->id,
            'exp_month' => 7,
            'exp_year' => 2030,
            'metadata' => ['safe' => true],
        ]);

        $this->assertTrue($user->paymentMethods->first()->is($paymentMethod));
        $this->assertTrue($paymentMethod->user->is($user));
        $this->assertTrue($paymentMethod->is_default);
        $this->assertSame(7, $paymentMethod->exp_month);
        $this->assertSame(2030, $paymentMethod->exp_year);
        $this->assertSame(['safe' => true], $paymentMethod->metadata);
    }

    public function test_raw_card_data_columns_do_not_exist(): void
    {
        $columns = Schema::getColumnListing('payment_methods');

        $this->assertNotContains('card_number', $columns);
        $this->assertNotContains('number', $columns);
        $this->assertNotContains('pan', $columns);
        $this->assertNotContains('cvv', $columns);
        $this->assertNotContains('cvc', $columns);
        $this->assertNotContains('security_code', $columns);
    }
}
