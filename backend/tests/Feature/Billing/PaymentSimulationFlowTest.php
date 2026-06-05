<?php

namespace Tests\Feature\Billing;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Permission;
use App\Models\Seller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSimulationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_simulate_permission_can_simulate_success(): void
    {
        $actor = $this->actorWithSimulatePermission();
        $payment = Payment::factory()->processing()->create();

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success", [
            'metadata' => ['scenario' => 'happy_path'],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'succeeded');

        $payment->refresh();

        $this->assertSame('succeeded', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNull($payment->failed_at);
        $this->assertSame(1, PaymentTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('type', 'payment_succeeded')
            ->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'billing.payment_simulated_success',
        ]);
    }

    public function test_user_with_simulate_permission_can_simulate_failure(): void
    {
        $actor = $this->actorWithSimulatePermission();
        $payment = Payment::factory()->processing()->create();

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/failure", [
            'reason' => 'card_declined',
            'metadata' => ['scenario' => 'decline'],
        ])->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $payment->refresh();

        $this->assertSame('failed', $payment->status);
        $this->assertSame('card_declined', $payment->failure_reason);
        $this->assertNotNull($payment->failed_at);
        $this->assertSame(1, PaymentTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('type', 'payment_failed')
            ->where('status_from', 'processing')
            ->where('status_to', 'failed')
            ->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'billing.payment_simulated_failure',
        ]);
    }

    public function test_user_without_permission_and_payment_owner_without_permission_cannot_simulate(): void
    {
        $owner = User::factory()->create();
        $payment = Payment::factory()->processing()->create([
            'user_id' => $owner->id,
            'payer_user_id' => $owner->id,
        ]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertForbidden();

        $this->assertSame('processing', $payment->refresh()->status);
        $this->assertSame(0, PaymentTransaction::query()->where('payment_id', $payment->id)->count());
    }

    public function test_final_payment_cannot_be_changed_to_another_final_state(): void
    {
        $this->actorWithSimulatePermission();
        $payment = Payment::factory()->succeeded()->create();

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/failure", [
            'reason' => 'manual_rejection',
        ])->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_already_final');

        $this->assertSame('succeeded', $payment->refresh()->status);
        $this->assertSame(0, PaymentTransaction::query()->where('payment_id', $payment->id)->count());
    }

    public function test_repeated_simulation_does_not_duplicate_transaction_side_effects(): void
    {
        $this->actorWithSimulatePermission();
        $payment = Payment::factory()->processing()->create();

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertOk();
        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');

        $this->assertSame(1, PaymentTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('type', 'payment_succeeded')
            ->count());
        $this->assertSame(1, ActivityLog::query()
            ->where('action', 'billing.payment_simulated_success')
            ->count());
    }

    public function test_invalid_metadata_is_rejected(): void
    {
        $this->actorWithSimulatePermission();
        $payment = Payment::factory()->processing()->create();

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success", [
            'metadata' => ['secret' => 'unsafe'],
            'card_number' => '4242424242424242',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['metadata.secret', 'card_number']);

        $this->assertSame('processing', $payment->refresh()->status);
    }

    public function test_non_simulator_external_provider_payment_cannot_be_simulated(): void
    {
        $this->actorWithSimulatePermission();
        $payment = Payment::factory()->processing()->create([
            'provider' => 'stripe',
            'provider_reference' => 'pi_fake_external_reference',
        ]);

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'payment_not_simulatable');

        $this->assertSame('processing', $payment->refresh()->status);
        $this->assertSame(0, PaymentTransaction::query()->where('payment_id', $payment->id)->count());
    }

    public function test_subscription_is_not_activated_and_ownership_fields_are_preserved(): void
    {
        $this->actorWithSimulatePermission();
        $payer = User::factory()->create();
        $company = Company::factory()->create();
        $seller = Seller::factory()->create([
            'company_id' => $company->id,
        ]);
        $subscription = Subscription::factory()->pending()->create([
            'user_id' => $payer->id,
        ]);
        $payment = Payment::factory()->processing()->create([
            'user_id' => $payer->id,
            'payer_user_id' => $payer->id,
            'subscription_id' => $subscription->id,
            'company_id' => $company->id,
            'seller_id' => $seller->id,
            'provider_account_id' => null,
        ]);

        $this->postJson("/api/v1/billing/payments/{$payment->id}/simulate/success")
            ->assertOk();

        $payment->refresh();

        $this->assertSame('pending', $subscription->refresh()->status);
        $this->assertSame($payer->id, $payment->payer_user_id);
        $this->assertSame($company->id, $payment->company_id);
        $this->assertSame($seller->id, $payment->seller_id);
    }

    public function test_pending_payment_can_be_simulated_to_success_or_failure(): void
    {
        $this->actorWithSimulatePermission();
        $success = Payment::factory()->pending()->create();
        $failure = Payment::factory()->pending()->create();

        $this->postJson("/api/v1/billing/payments/{$success->id}/simulate/success")
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');

        $this->postJson("/api/v1/billing/payments/{$failure->id}/simulate/failure", [
            'reason' => 'provider_timeout',
        ])->assertOk()
            ->assertJsonPath('data.status', 'failed');
    }

    private function actorWithSimulatePermission(): User
    {
        $actor = User::factory()->create();
        $permission = Permission::query()->firstOrCreate(
            ['name' => 'billing.payments.simulate'],
            ['description' => 'Simulate payment outcomes'],
        );
        $actor->permissions()->syncWithoutDetaching([$permission->id]);

        Sanctum::actingAs($actor);

        return $actor;
    }
}
