<?php

namespace Tests\Feature\Billing;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_returns_only_current_users_methods(): void
    {
        $user = $this->actingUser();
        PaymentMethod::factory()->fakeCard()->create(['user_id' => $user->id, 'last4' => '1111']);
        PaymentMethod::factory()->fakeCard()->create(['last4' => '9999']);

        $this->getJson('/api/v1/billing/payment-methods')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last4', '1111');
    }

    public function test_create_fake_card_rejects_raw_card_data_and_unsafe_metadata(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/billing/payment-methods', [
            'type' => 'fake_card',
            'brand' => 'visa',
            'last4' => '4242',
            'card_number' => '4242424242424242',
            'metadata' => ['token' => 'secret'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['card_number', 'metadata.token']);
    }

    public function test_create_set_default_and_deactivate_payment_method(): void
    {
        $user = $this->actingUser();

        $response = $this->postJson('/api/v1/billing/payment-methods', [
            'type' => 'fake_card',
            'brand' => 'mastercard',
            'last4' => '1111',
            'metadata' => ['source' => 'api_test'],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'fake_card')
            ->assertJsonPath('data.last4', '1111')
            ->assertJsonMissingPath('data.provider_reference');

        $paymentMethod = PaymentMethod::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

        $this->postJson("/api/v1/billing/payment-methods/{$paymentMethod->id}/set-default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertSame($paymentMethod->id, $user->refresh()->paymentPreference->default_payment_method_id);

        $this->deleteJson("/api/v1/billing/payment-methods/{$paymentMethod->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.is_default', false);
    }

    public function test_cannot_set_another_users_or_inactive_method_default(): void
    {
        $user = $this->actingUser();
        $otherMethod = PaymentMethod::factory()->fakeCard()->create();
        $inactive = PaymentMethod::factory()->fakeCard()->inactive()->create(['user_id' => $user->id]);

        $this->postJson("/api/v1/billing/payment-methods/{$otherMethod->id}/set-default")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_method_does_not_belong_to_user');

        $this->postJson("/api/v1/billing/payment-methods/{$inactive->id}/set-default")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_method_not_allowed');
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
