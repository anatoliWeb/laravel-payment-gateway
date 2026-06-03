<?php

namespace App\DTO\Payments;

use App\Http\Requests\Api\V1\Billing\CreatePaymentRequest;
use App\Models\User;

final readonly class CreatePaymentData
{
    public function __construct(
        public User $user,
        public ?int $subscriptionId,
        public ?string $planSlug,
        public ?int $amount,
        public string $currency,
        public ?string $paymentSource,
        public ?string $paymentStrategy,
        public ?int $paymentMethodId,
        public ?string $callbackUrl,
        public ?string $description,
        public array $metadata,
        public string $idempotencyKey,
    ) {
    }

    public static function fromRequest(CreatePaymentRequest $request): self
    {
        /** @var User $user */
        $user = $request->user();

        return new self(
            user: $user,
            subscriptionId: $request->integer('subscription_id') ?: null,
            planSlug: $request->input('plan_slug'),
            amount: $request->integer('amount') ?: null,
            currency: strtoupper((string) $request->input('currency')),
            paymentSource: $request->input('payment_source'),
            paymentStrategy: $request->input('payment_strategy'),
            paymentMethodId: $request->integer('payment_method_id') ?: null,
            callbackUrl: $request->input('callback_url'),
            description: $request->input('description'),
            metadata: (array) $request->input('metadata', []),
            idempotencyKey: (string) $request->header('Idempotency-Key'),
        );
    }
}
