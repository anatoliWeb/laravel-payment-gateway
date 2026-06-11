<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingApiResponseContractTest extends TestCase
{
    use DatabaseTransactions;

    public function test_billing_success_responses_use_the_unified_envelope(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/billing/wallet')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('code');
    }

    public function test_billing_validation_errors_return_stable_code_and_field_errors(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/billing/payments', [
            'card_number' => '4242424242424242',
            'metadata' => [
                'private_key' => 'super-secret-value',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['success', 'message', 'code', 'errors']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('super-secret-value', $body);
        $this->assertStringNotContainsString('4242424242424242', $body);
    }
}
