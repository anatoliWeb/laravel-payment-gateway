<?php

namespace Tests\Feature\Billing;

use App\DTO\Payments\CreatePaymentData;
use App\Models\Currency;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use App\Services\Billing\WalletTransactionService;
use App\Services\Payments\IdempotencyService;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_same_key_and_payload_replays_payment_method_payment(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);
        $payload = ['amount' => 1200, 'currency' => 'USD', 'payment_source' => 'payment_method'];

        $first = $this->withHeader('Idempotency-Key', 'payment-replay')->postJson('/api/v1/billing/payments', $payload)->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'payment-replay')->postJson('/api/v1/billing/payments', $payload)->assertCreated();

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertSame(1, Payment::query()->count());
        $record = IdempotencyKey::query()->where('scope', 'payment.create')->firstOrFail();
        $this->assertSame('completed', $record->status);
        $this->assertNotSame('payment-replay', $record->key);
    }

    public function test_same_key_with_different_payload_returns_conflict(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);

        $this->withHeader('Idempotency-Key', 'payment-conflict')
            ->postJson('/api/v1/billing/payments', ['amount' => 1200, 'currency' => 'USD', 'payment_source' => 'payment_method'])
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'payment-conflict')
            ->postJson('/api/v1/billing/payments', ['amount' => 1300, 'currency' => 'USD', 'payment_source' => 'payment_method'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'idempotency_key_conflict');

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_duplicate_wallet_and_wallet_first_payments_do_not_repeat_side_effects(): void
    {
        $user = $this->actingUser();
        $this->activeCurrency('USD');
        PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);
        app(WalletTransactionService::class)->credit($user, 'USD', 5000);

        $walletPayload = ['amount' => 1200, 'currency' => 'USD', 'payment_source' => 'wallet'];
        $this->withHeader('Idempotency-Key', 'wallet-payment-replay')->postJson('/api/v1/billing/payments', $walletPayload)->assertCreated();
        $this->withHeader('Idempotency-Key', 'wallet-payment-replay')->postJson('/api/v1/billing/payments', $walletPayload)->assertCreated();

        $fallbackPayload = ['amount' => 6000, 'currency' => 'USD', 'payment_source' => 'wallet_first'];
        $this->withHeader('Idempotency-Key', 'wallet-first-replay')->postJson('/api/v1/billing/payments', $fallbackPayload)->assertCreated();
        $this->withHeader('Idempotency-Key', 'wallet-first-replay')->postJson('/api/v1/billing/payments', $fallbackPayload)->assertCreated();

        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(1, WalletTransaction::query()->where('type', 'debit')->count());
        $this->assertSame(3800, app(WalletService::class)->getBalance($user->refresh(), 'USD')->available_amount);
    }

    public function test_different_users_can_reuse_same_key_independently(): void
    {
        $this->activeCurrency('USD');
        $payload = ['amount' => 1200, 'currency' => 'USD', 'payment_source' => 'payment_method'];

        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            PaymentMethod::factory()->fakeCard()->default()->create(['user_id' => $user->id]);
            Sanctum::actingAs($user);
            $this->withHeader('Idempotency-Key', 'shared-client-key')
                ->postJson('/api/v1/billing/payments', $payload)
                ->assertCreated();
        }

        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(2, IdempotencyKey::query()->where('scope', 'payment.create')->count());
    }

    public function test_payment_service_returns_stable_missing_key_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('idempotency_key_required');

        app(PaymentService::class)->createPayment(new CreatePaymentData(
            user: User::factory()->create(),
            subscriptionId: null,
            planSlug: null,
            amount: 1000,
            currency: 'USD',
            paymentSource: 'wallet',
            paymentStrategy: null,
            paymentMethodId: null,
            callbackUrl: null,
            description: null,
            metadata: [],
            idempotencyKey: '',
        ));
    }

    public function test_processing_request_is_blocked_and_expired_record_can_restart(): void
    {
        $user = User::factory()->create();
        $service = app(IdempotencyService::class);
        $payload = ['amount' => 1000, 'currency' => 'USD'];
        $record = $service->start('processing-key', 'payment.create', $payload, $user);

        try {
            $service->start('processing-key', 'payment.create', $payload, $user);
            $this->fail('Expected processing request to be blocked.');
        } catch (RuntimeException $exception) {
            $this->assertSame('idempotency_request_processing', $exception->getMessage());
        }

        $record->update([
            'status' => 'completed',
            'response_body' => ['payment_id' => 999],
            'locked_until' => null,
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertNull($service->replay('processing-key', 'payment.create', $payload, $user));
        $restarted = $service->start('processing-key', 'payment.create', ['amount' => 2000, 'currency' => 'USD'], $user);
        $this->assertSame('processing', $restarted->status);
        $this->assertSame(
            $service->payloadHash(['amount' => 2000, 'currency' => 'USD']),
            $restarted->request_hash,
        );
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
