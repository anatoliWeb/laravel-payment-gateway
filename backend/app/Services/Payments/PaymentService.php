<?php

namespace App\Services\Payments;

use App\DTO\Payments\CreatePaymentData;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\ActivityService;
use App\Services\Billing\WalletService;
use App\Services\Billing\WalletTransactionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PaymentService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly WalletTransactionService $walletTransactionService,
        private readonly ActivityService $activityService,
        private readonly PaymentRiskService $paymentRiskService,
    ) {
    }

    public function createPayment(CreatePaymentData $data): Payment
    {
        if (trim($data->idempotencyKey) === '') {
            throw new RuntimeException('idempotency_key_missing');
        }

        $currency = $this->resolveCurrency($data->currency);
        [$subscription, $plan, $amount] = $this->resolveContextAndAmount($data, $currency);
        $source = $this->resolvePaymentSource($data, $amount, $currency->code);
        $risk = $this->paymentRiskService->checkBeforePaymentCreation($data, $amount, $currency->code, $source);

        if (! $risk['allowed']) {
            throw new RuntimeException($risk['reason'] ?? 'risk_check_failed');
        }

        return DB::transaction(function () use ($data, $currency, $subscription, $plan, $amount, $source): Payment {
            return match ($source) {
                'wallet' => $this->createWalletPayment($data, $currency->code, $subscription, $plan, $amount),
                'payment_method' => $this->createPaymentMethodPayment($data, $currency->code, $subscription, $plan, $amount),
                'wallet_first' => $this->createWalletFirstPayment($data, $currency->code, $subscription, $plan, $amount),
                default => throw new RuntimeException('invalid_payment_source'),
            };
        });
    }

    private function createWalletPayment(
        CreatePaymentData $data,
        string $currency,
        ?Subscription $subscription,
        ?Plan $plan,
        int $amount,
    ): Payment {
        $this->assertWalletCanPay($data->user, $currency, $amount);

        $payment = $this->createBasePayment(
            data: $data,
            currency: $currency,
            subscription: $subscription,
            plan: $plan,
            amount: $amount,
            source: 'wallet',
            status: 'succeeded',
            paymentMethod: 'fake_wallet',
            provider: 'internal_wallet',
            providerReference: 'wallet_'.Str::lower(Str::random(16)),
            paidAt: now(),
        );

        $walletTransaction = $this->walletTransactionService->debit(
            user: $data->user,
            currencyCode: $currency,
            amount: $amount,
            idempotencyKey: $this->walletIdempotencyKey($data),
            metadata: [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription?->id,
                'reason' => 'payment_created',
                'source' => 'payment_service',
            ],
        );

        $payment->metadata = array_merge($payment->metadata ?? [], [
            'wallet_transaction_id' => $walletTransaction->id,
        ]);
        $payment->save();

        $this->recordInitialTransaction($payment, 'Payment created from wallet balance.', [
            'payment_source' => 'wallet',
            'wallet_transaction_id' => $walletTransaction->id,
            'idempotency_key_hash' => hash('sha256', $data->idempotencyKey),
        ]);
        $this->recordActivity($payment, 'wallet', $walletTransaction);

        return $payment->refresh();
    }

    private function createPaymentMethodPayment(
        CreatePaymentData $data,
        string $currency,
        ?Subscription $subscription,
        ?Plan $plan,
        int $amount,
        string $source = 'payment_method',
    ): Payment {
        $paymentMethod = $this->resolvePaymentMethod($data);
        [$provider, $status, $providerReference] = $this->resolveSimulatorProviderResult($paymentMethod);

        $payment = $this->createBasePayment(
            data: $data,
            currency: $currency,
            subscription: $subscription,
            plan: $plan,
            amount: $amount,
            source: $source,
            status: $status,
            paymentMethod: $paymentMethod->type,
            provider: $provider,
            providerReference: $providerReference,
            paidAt: null,
        );

        $payment->metadata = array_merge($payment->metadata ?? [], [
            'payment_method_id' => $paymentMethod->id,
            'payment_method_uuid' => $paymentMethod->uuid,
            'payment_method_type' => $paymentMethod->type,
        ]);
        $payment->save();

        $this->recordInitialTransaction($payment, 'Payment created with simulator payment method.', [
            'payment_source' => $source,
            'payment_method_id' => $paymentMethod->id,
            'payment_method_type' => $paymentMethod->type,
            'idempotency_key_hash' => hash('sha256', $data->idempotencyKey),
        ]);
        $this->recordActivity($payment, 'payment_method');

        return $payment->refresh();
    }

    private function createWalletFirstPayment(
        CreatePaymentData $data,
        string $currency,
        ?Subscription $subscription,
        ?Plan $plan,
        int $amount,
    ): Payment {
        if ($this->walletHasEnoughBalance($data->user, $currency, $amount)) {
            return $this->createWalletPayment($data, $currency, $subscription, $plan, $amount);
        }

        return $this->createPaymentMethodPayment($data, $currency, $subscription, $plan, $amount, 'wallet_first');
    }

    private function createBasePayment(
        CreatePaymentData $data,
        string $currency,
        ?Subscription $subscription,
        ?Plan $plan,
        int $amount,
        string $source,
        string $status,
        string $paymentMethod,
        string $provider,
        string $providerReference,
        mixed $paidAt,
    ): Payment {
        return Payment::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $data->user->id,
            'subscription_id' => $subscription?->id,
            'invoice_id' => null,
            'parent_payment_id' => null,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'payment_method' => $paymentMethod,
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'description' => $data->description,
            'failure_reason' => null,
            'callback_url' => $data->callbackUrl,
            'metadata' => array_merge($this->sanitizeMetadata($data->metadata), [
                'source' => 'payment_service',
                'payment_source' => $source,
                'plan_slug' => $plan?->slug,
                'idempotency_key_hash' => hash('sha256', $data->idempotencyKey),
            ]),
            'paid_at' => $paidAt,
        ]);
    }

    private function recordInitialTransaction(Payment $payment, string $message, array $payload): void
    {
        PaymentTransaction::query()->create([
            'payment_id' => $payment->id,
            'type' => 'payment_created',
            'status_from' => null,
            'status_to' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'message' => $message,
            'payload' => $this->sanitizeMetadata(array_merge([
                'source' => 'payment_service',
                'provider' => $payment->provider,
            ], $payload)),
        ]);
    }

    private function recordActivity(Payment $payment, string $source, ?WalletTransaction $walletTransaction = null): void
    {
        try {
            $this->activityService->log($payment->user_id, 'billing.payment_created', 'Billing payment created', [
                'source' => 'payment_service',
                'module' => 'billing',
                'payment_id' => $payment->id,
                'payment_uuid' => $payment->uuid,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_source' => $source,
                'wallet_transaction_id' => $walletTransaction?->id,
            ]);
        } catch (Throwable) {
            // Activity logging must not break payment creation.
        }
    }

    private function resolveCurrency(string $currencyCode): Currency
    {
        $currency = Currency::query()
            ->where('code', strtoupper($currencyCode))
            ->where('is_active', true)
            ->first();

        if (! $currency) {
            throw new RuntimeException('payment_currency_not_available');
        }

        return $currency;
    }

    /**
     * @return array{0: ?Subscription, 1: ?Plan, 2: int}
     */
    private function resolveContextAndAmount(CreatePaymentData $data, Currency $currency): array
    {
        $subscription = null;
        $plan = null;

        if ($data->subscriptionId !== null) {
            $subscription = Subscription::query()
                ->where('id', $data->subscriptionId)
                ->where('user_id', $data->user->id)
                ->first();

            if (! $subscription) {
                throw new RuntimeException('subscription_not_found');
            }

            $plan = $subscription->plan;
        }

        if ($data->planSlug !== null) {
            $plan = Plan::query()->active()->bySlug($data->planSlug)->first();

            if (! $plan) {
                throw new RuntimeException('plan_not_available');
            }
        }

        if ($plan && strtoupper($plan->currency) !== $currency->code) {
            throw new RuntimeException('payment_currency_conflict');
        }

        $amount = $plan ? (int) $plan->price_amount : (int) $data->amount;

        if ($data->amount !== null && $plan && (int) $data->amount !== $amount) {
            throw new RuntimeException('payment_amount_conflict');
        }

        if ($amount <= 0) {
            throw new RuntimeException('payment_amount_must_be_positive');
        }

        return [$subscription, $plan, $amount];
    }

    private function resolvePaymentSource(CreatePaymentData $data, int $amount, string $currency): string
    {
        if ($data->paymentSource !== null) {
            return $data->paymentSource;
        }

        $strategy = $data->paymentStrategy
            ?? $data->user->paymentPreference?->strategy;

        return match ($strategy) {
            'wallet_only' => 'wallet',
            'payment_method_only' => 'payment_method',
            'wallet_first' => 'wallet_first',
            'manual_invoice' => $this->manualInvoiceSource($data),
            null => $this->defaultPaymentSource($data->user, $amount, $currency),
            default => throw new RuntimeException('invalid_payment_strategy'),
        };
    }

    private function manualInvoiceSource(CreatePaymentData $data): string
    {
        $manual = PaymentMethod::query()
            ->where('user_id', $data->user->id)
            ->where('type', 'fake_manual_invoice')
            ->where('status', 'active')
            ->first();

        if (! $manual) {
            throw new RuntimeException('manual_invoice_not_supported');
        }

        return 'payment_method';
    }

    private function defaultPaymentSource(User $user, int $amount, string $currency): string
    {
        if ($this->activeDefaultPaymentMethod($user)) {
            return 'payment_method';
        }

        if ($this->walletHasEnoughBalance($user, $currency, $amount)) {
            return 'wallet';
        }

        throw new RuntimeException('payment_source_not_available');
    }

    private function resolvePaymentMethod(CreatePaymentData $data): PaymentMethod
    {
        if ($data->paymentMethodId !== null) {
            $paymentMethod = PaymentMethod::query()->whereKey($data->paymentMethodId)->first();

            if ($paymentMethod && (int) $paymentMethod->user_id !== (int) $data->user->id) {
                throw new RuntimeException('payment_method_does_not_belong_to_user');
            }

            if (! $paymentMethod || $paymentMethod->status !== 'active') {
                throw new RuntimeException('payment_method_not_found');
            }

            return $paymentMethod;
        }

        if ($data->paymentStrategy === 'manual_invoice') {
            $paymentMethod = PaymentMethod::query()
                ->where('user_id', $data->user->id)
                ->where('type', 'fake_manual_invoice')
                ->where('status', 'active')
                ->first();

            if (! $paymentMethod) {
                throw new RuntimeException('manual_invoice_not_supported');
            }

            return $paymentMethod;
        }

        $paymentMethod = $this->activeDefaultPaymentMethod($data->user);

        if (! $paymentMethod) {
            throw new RuntimeException('payment_method_not_found');
        }

        return $paymentMethod;
    }

    private function activeDefaultPaymentMethod(User $user): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('is_default', true)
            ->first();
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveSimulatorProviderResult(PaymentMethod $paymentMethod): array
    {
        return match ($paymentMethod->type) {
            'fake_card' => ['simulator', 'processing', 'sim_'.Str::lower(Str::random(16))],
            'fake_manual_invoice' => ['manual', 'pending', 'manual_'.Str::lower(Str::random(16))],
            'fake_wallet' => ['internal_wallet', 'pending', 'wallet_'.Str::lower(Str::random(16))],
            default => throw new RuntimeException('external_provider_disabled'),
        };
    }

    private function assertWalletCanPay(User $user, string $currency, int $amount): void
    {
        if (! $this->walletHasEnoughBalance($user, $currency, $amount)) {
            throw new RuntimeException('insufficient_wallet_balance');
        }
    }

    private function walletHasEnoughBalance(User $user, string $currency, int $amount): bool
    {
        $balance = $this->walletService->getBalance($user, $currency);

        return $balance !== null && $balance->available_amount >= $amount;
    }

    private function walletIdempotencyKey(CreatePaymentData $data): string
    {
        return 'payment:'.$data->idempotencyKey.':wallet_debit';
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $forbidden = [
            'card_number',
            'pan',
            'cvv',
            'cvc',
            'security_code',
            'token',
            'secret',
            'password',
            'private_key',
        ];

        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), $forbidden, true)) {
                unset($metadata[$key]);
                continue;
            }

            if (is_array($value)) {
                $metadata[$key] = $this->sanitizeMetadata($value);
            }
        }

        return $metadata;
    }
}
