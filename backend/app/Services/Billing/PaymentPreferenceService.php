<?php

namespace App\Services\Billing;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\UserPaymentPreference;
use App\Services\ActivityService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PaymentPreferenceService
{
    private const STRATEGIES = [
        'wallet_only',
        'payment_method_only',
        'wallet_first',
        'manual_invoice',
    ];

    public function __construct(
        private readonly ActivityService $activityService,
    ) {
    }

    public function getOrCreatePreferences(User $user): UserPaymentPreference
    {
        return DB::transaction(function () use ($user): UserPaymentPreference {
            $preference = UserPaymentPreference::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($preference) {
                return $preference;
            }

            return UserPaymentPreference::query()->create([
                'user_id' => $user->id,
                'strategy' => 'wallet_first',
                'auto_charge_enabled' => false,
                'auto_top_up_enabled' => false,
                'metadata' => ['source' => 'payment_preference_service'],
            ]);
        });
    }

    public function setStrategy(User $user, string $strategy): UserPaymentPreference
    {
        if (! in_array($strategy, self::STRATEGIES, true)) {
            throw new RuntimeException('invalid_payment_strategy');
        }

        $preference = $this->getOrCreatePreferences($user);
        $preference->forceFill(['strategy' => $strategy])->save();

        return $preference->refresh();
    }

    public function enableAutoCharge(User $user): UserPaymentPreference
    {
        $preference = $this->getOrCreatePreferences($user);
        $preference->forceFill([
            'auto_charge_enabled' => true,
            'auto_charge_consent_at' => now(),
        ])->save();

        return $preference->refresh();
    }

    public function disableAutoCharge(User $user): UserPaymentPreference
    {
        $preference = $this->getOrCreatePreferences($user);
        $preference->forceFill(['auto_charge_enabled' => false])->save();

        return $preference->refresh();
    }

    public function enableAutoTopUp(
        User $user,
        int $thresholdAmount,
        int $topUpAmount,
        string $currencyCode
    ): UserPaymentPreference {
        if ($thresholdAmount < 0 || $topUpAmount <= 0) {
            throw new RuntimeException('invalid_auto_top_up_amount');
        }

        $currency = Currency::query()
            ->where('code', strtoupper($currencyCode))
            ->where('is_active', true)
            ->first();

        if (! $currency) {
            throw new RuntimeException('auto_top_up_currency_not_available');
        }

        $preference = $this->getOrCreatePreferences($user);
        $preference->forceFill([
            'auto_top_up_enabled' => true,
            'auto_top_up_threshold_amount' => $thresholdAmount,
            'auto_top_up_amount' => $topUpAmount,
            'auto_top_up_currency_id' => $currency->id,
            'max_auto_top_up_per_day' => $preference->max_auto_top_up_per_day ?? 3,
            'max_auto_top_up_per_month' => $preference->max_auto_top_up_per_month ?? 10,
            'auto_top_up_consent_at' => now(),
        ])->save();

        return $preference->refresh();
    }

    public function disableAutoTopUp(User $user): UserPaymentPreference
    {
        $preference = $this->getOrCreatePreferences($user);
        $preference->forceFill(['auto_top_up_enabled' => false])->save();

        return $preference->refresh();
    }

    public function updatePreferences(User $user, array $data): UserPaymentPreference
    {
        return DB::transaction(function () use ($user, $data): UserPaymentPreference {
            $preference = $this->getOrCreatePreferences($user);
            $autoChargeBefore = (bool) $preference->auto_charge_enabled;

            if (array_key_exists('strategy', $data)) {
                if (! in_array($data['strategy'], self::STRATEGIES, true)) {
                    throw new RuntimeException('invalid_payment_strategy');
                }

                $preference->strategy = $data['strategy'];
            }

            if (array_key_exists('default_payment_method_id', $data)) {
                $preference->default_payment_method_id = $this->resolveDefaultPaymentMethodId($user, $data['default_payment_method_id']);
                PaymentMethod::query()
                    ->where('user_id', $user->id)
                    ->update(['is_default' => false]);

                if ($preference->default_payment_method_id !== null) {
                    PaymentMethod::query()
                        ->whereKey($preference->default_payment_method_id)
                        ->update(['is_default' => true]);
                }
            }

            if (array_key_exists('auto_charge_enabled', $data)) {
                $enabled = (bool) $data['auto_charge_enabled'];
                $preference->auto_charge_enabled = $enabled;

                if ($enabled && ! $preference->auto_charge_consent_at) {
                    $preference->auto_charge_consent_at = now();
                }
            }

            if (array_key_exists('auto_top_up_enabled', $data)) {
                $preference->auto_top_up_enabled = (bool) $data['auto_top_up_enabled'];

                if ($preference->auto_top_up_enabled && ! $preference->auto_top_up_consent_at) {
                    $preference->auto_top_up_consent_at = now();
                }
            }

            foreach ([
                'auto_top_up_threshold_amount',
                'auto_top_up_amount',
                'max_auto_top_up_per_day',
                'max_auto_top_up_per_month',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $preference->{$field} = $data[$field];
                }
            }

            if (array_key_exists('auto_top_up_currency', $data)) {
                $preference->auto_top_up_currency_id = $this->resolveAutoTopUpCurrencyId((string) $data['auto_top_up_currency']);
            }

            if ($preference->auto_top_up_enabled) {
                $this->assertAutoTopUpSettingsAreValid($preference);
            }

            $preference->save();

            if ($autoChargeBefore !== (bool) $preference->auto_charge_enabled) {
                $this->recordActivity($user, 'billing.auto_charge_consent_changed', [
                    'enabled' => (bool) $preference->auto_charge_enabled,
                ]);
            }

            return $preference->refresh();
        });
    }

    public function setDefaultPaymentMethod(User $user, PaymentMethod $paymentMethod): UserPaymentPreference
    {
        if ((int) $paymentMethod->user_id !== (int) $user->id) {
            throw new RuntimeException('payment_method_does_not_belong_to_user');
        }

        $preference = $this->getOrCreatePreferences($user);
        $preference->forceFill(['default_payment_method_id' => $paymentMethod->id])->save();

        return $preference->refresh();
    }

    private function resolveDefaultPaymentMethodId(User $user, mixed $paymentMethodId): ?int
    {
        if ($paymentMethodId === null || $paymentMethodId === '') {
            return null;
        }

        $paymentMethod = PaymentMethod::query()->whereKey((int) $paymentMethodId)->first();

        if (! $paymentMethod || (int) $paymentMethod->user_id !== (int) $user->id) {
            throw new RuntimeException('payment_method_not_found');
        }

        if ($paymentMethod->status !== 'active') {
            throw new RuntimeException('payment_method_not_allowed');
        }

        return $paymentMethod->id;
    }

    private function resolveAutoTopUpCurrencyId(string $currencyCode): int
    {
        $currency = Currency::query()
            ->where('code', strtoupper($currencyCode))
            ->where('is_active', true)
            ->first();

        if (! $currency) {
            throw new RuntimeException('auto_top_up_currency_not_available');
        }

        return $currency->id;
    }

    private function assertAutoTopUpSettingsAreValid(UserPaymentPreference $preference): void
    {
        if ((int) $preference->auto_top_up_threshold_amount < 0 || (int) $preference->auto_top_up_amount <= 0) {
            throw new RuntimeException('invalid_auto_top_up_amount');
        }

        if (! $preference->auto_top_up_currency_id) {
            throw new RuntimeException('auto_top_up_currency_not_available');
        }
    }

    private function recordActivity(User $user, string $action, array $metadata): void
    {
        try {
            $this->activityService->log($user->id, $action, 'Billing payment preference changed', array_merge([
                'source' => 'payment_preference_service',
                'module' => 'billing',
            ], $metadata));
        } catch (Throwable) {
            // Preference updates must not fail because activity logging failed.
        }
    }
}
