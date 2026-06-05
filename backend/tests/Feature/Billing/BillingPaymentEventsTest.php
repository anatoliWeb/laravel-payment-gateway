<?php

namespace Tests\Feature\Billing;

use App\Events\Billing\PaymentCreated;
use App\Events\Billing\PaymentFailed;
use App\Events\Billing\PaymentSucceeded;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\User;
use App\Services\Payments\PaymentSimulationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingPaymentEventsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_creation_dispatches_payment_created_once_on_idempotent_replay(): void
    {
        Event::fake([PaymentCreated::class]);
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);
        $payload = ['amount' => 1200, 'currency' => 'USD', 'payment_source' => 'payment_method'];

        $this->withHeader('Idempotency-Key', 'event-payment-create')
            ->postJson('/api/v1/billing/payments', $payload)
            ->assertCreated();
        $this->withHeader('Idempotency-Key', 'event-payment-create')
            ->postJson('/api/v1/billing/payments', $payload)
            ->assertCreated();

        Event::assertDispatchedTimes(PaymentCreated::class, 1);
    }

    public function test_payment_success_and_failure_events_are_dispatched_once(): void
    {
        Event::fake([PaymentSucceeded::class, PaymentFailed::class]);
        $actor = User::factory()->create();
        $success = Payment::factory()->processing()->create();
        $failure = Payment::factory()->processing()->create();
        $service = app(PaymentSimulationService::class);

        $service->simulateSuccess($success, $actor);
        $service->simulateSuccess($success->refresh(), $actor);
        $service->simulateFailure($failure, $actor, 'card_declined');

        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
        Event::assertDispatchedTimes(PaymentFailed::class, 1);
    }

    public function test_payment_event_payload_is_safe(): void
    {
        Event::fake([PaymentCreated::class]);
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);

        $this->withHeader('Idempotency-Key', 'event-safe-payload')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'payment_method',
                'metadata' => ['secret' => 'hidden'],
            ])
            ->assertStatus(422);

        $this->withHeader('Idempotency-Key', 'event-safe-payload-valid')
            ->postJson('/api/v1/billing/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'payment_source' => 'payment_method',
                'metadata' => ['safe' => 'ok'],
            ])
            ->assertCreated();

        Event::assertDispatched(PaymentCreated::class, function (PaymentCreated $event): bool {
            $encoded = json_encode($event->payload, JSON_THROW_ON_ERROR);

            return ! str_contains($encoded, 'secret')
                && ! str_contains($encoded, 'idempotency')
                && ! str_contains($encoded, 'card_number');
        });
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function activeCurrency(string $code): Currency
    {
        return Currency::factory()->create([
            'code' => $code,
            'name' => "{$code} Currency",
            'symbol' => $code,
            'is_active' => true,
            'is_base' => $code === 'USD',
        ]);
    }
}
