<?php

namespace App\Services\Billing;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentMethodService
{
    private const ALLOWED_CARD_DATA = [
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'display_name',
        'metadata',
    ];

    public function getUserPaymentMethods(User $user): Collection
    {
        return PaymentMethod::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getDefaultPaymentMethod(User $user): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->first();
    }

    public function createFakeCard(User $user, array $data): PaymentMethod
    {
        $this->assertNoRawCardData($data);

        $safeData = array_intersect_key($data, array_flip(self::ALLOWED_CARD_DATA));
        $brand = $safeData['brand'] ?? 'visa';
        $last4 = $safeData['last4'] ?? '4242';

        return PaymentMethod::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'fake_card',
            'provider' => 'simulator',
            'status' => 'active',
            'display_name' => $safeData['display_name'] ?? $this->maskedCardLabel($brand, $last4),
            'brand' => $brand,
            'last4' => $last4,
            'exp_month' => $safeData['exp_month'] ?? 12,
            'exp_year' => $safeData['exp_year'] ?? now()->addYears(3)->year,
            'provider_reference' => 'sim_pm_'.Str::lower(Str::random(16)),
            'is_default' => false,
            'consent_given_at' => now(),
            'metadata' => $this->safeMetadata($safeData['metadata'] ?? []),
        ]);
    }

    public function createManualInvoiceMethod(User $user): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'fake_manual_invoice',
            'provider' => 'manual',
            'status' => 'active',
            'display_name' => 'Manual invoice',
            'brand' => null,
            'last4' => null,
            'exp_month' => null,
            'exp_year' => null,
            'provider_reference' => 'manual_'.Str::lower(Str::random(16)),
            'is_default' => false,
            'consent_given_at' => now(),
            'metadata' => ['source' => 'payment_method_service'],
        ]);
    }

    public function createWalletMethod(User $user): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'fake_wallet',
            'provider' => 'internal_wallet',
            'status' => 'active',
            'display_name' => 'Internal wallet balance',
            'brand' => 'wallet',
            'last4' => null,
            'exp_month' => null,
            'exp_year' => null,
            'provider_reference' => 'wallet_'.Str::lower(Str::random(16)),
            'is_default' => false,
            'consent_given_at' => now(),
            'metadata' => ['source' => 'payment_method_service'],
        ]);
    }

    public function createPaymentMethod(User $user, array $data): PaymentMethod
    {
        return match ($data['type'] ?? 'fake_card') {
            'fake_card' => $this->createFakeCard($user, $data),
            'fake_manual_invoice' => $this->createManualInvoiceMethod($user),
            'fake_wallet' => $this->createWalletMethod($user),
            default => throw new RuntimeException('payment_method_type_not_supported'),
        };
    }

    public function updatePaymentMethod(User $user, PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $this->assertBelongsToUser($user, $paymentMethod);
        $this->assertNoRawCardData($data);

        return DB::transaction(function () use ($user, $paymentMethod, $data): PaymentMethod {
            $updates = array_intersect_key($data, array_flip([
                'display_name',
                'status',
                'metadata',
            ]));

            if (array_key_exists('metadata', $updates)) {
                $updates['metadata'] = $this->safeMetadata((array) $updates['metadata']);
            }

            $paymentMethod->forceFill($updates)->save();

            if (in_array($paymentMethod->status, ['inactive', 'revoked'], true)) {
                $paymentMethod->forceFill(['is_default' => false])->save();

                $preference = $user->paymentPreference;
                if ($preference?->default_payment_method_id === $paymentMethod->id) {
                    $preference->forceFill(['default_payment_method_id' => null])->save();
                }
            }

            return $paymentMethod->refresh();
        });
    }

    public function setDefaultPaymentMethod(User $user, PaymentMethod $paymentMethod): PaymentMethod
    {
        $this->assertBelongsToUser($user, $paymentMethod);

        return DB::transaction(function () use ($user, $paymentMethod): PaymentMethod {
            PaymentMethod::query()
                ->where('user_id', $user->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $paymentMethod->forceFill(['is_default' => true])->save();

            app(PaymentPreferenceService::class)->setDefaultPaymentMethod($user, $paymentMethod);

            return $paymentMethod->refresh();
        });
    }

    public function deactivatePaymentMethod(User $user, PaymentMethod $paymentMethod): PaymentMethod
    {
        $this->assertBelongsToUser($user, $paymentMethod);

        return DB::transaction(function () use ($user, $paymentMethod): PaymentMethod {
            $paymentMethod->forceFill([
                'status' => 'inactive',
                'is_default' => false,
            ])->save();

            $preference = $user->paymentPreference;
            if ($preference?->default_payment_method_id === $paymentMethod->id) {
                $preference->forceFill(['default_payment_method_id' => null])->save();
            }

            return $paymentMethod->refresh();
        });
    }

    private function assertBelongsToUser(User $user, PaymentMethod $paymentMethod): void
    {
        if ((int) $paymentMethod->user_id !== (int) $user->id) {
            throw new RuntimeException('payment_method_does_not_belong_to_user');
        }
    }

    private function assertNoRawCardData(array $data): void
    {
        foreach (['card_number', 'number', 'pan', 'cvv', 'cvc', 'security_code'] as $forbiddenKey) {
            if (array_key_exists($forbiddenKey, $data)) {
                throw new RuntimeException('raw_card_data_not_allowed');
            }
        }
    }

    private function maskedCardLabel(string $brand, string $last4): string
    {
        return Str::title($brand).' ending '.$last4;
    }

    private function safeMetadata(array $metadata): array
    {
        $forbidden = [
            'card_number',
            'number',
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
                $metadata[$key] = $this->safeMetadata($value);
            }
        }

        return $metadata;
    }
}
