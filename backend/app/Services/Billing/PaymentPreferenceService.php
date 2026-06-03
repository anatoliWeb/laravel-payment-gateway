<?php

namespace App\Services\Billing;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\UserPaymentPreference;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentPreferenceService
{
    private const STRATEGIES = [
        'wallet_only',
        'payment_method_only',
        'wallet_first',
        'manual_invoice',
    ];

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

    public function setDefaultPaymentMethod(User $user, PaymentMethod $paymentMethod): UserPaymentPreference
    {
        if ((int) $paymentMethod->user_id !== (int) $user->id) {
            throw new RuntimeException('payment_method_does_not_belong_to_user');
        }

        $preference = $this->getOrCreatePreferences($user);
        $preference->forceFill(['default_payment_method_id' => $paymentMethod->id])->save();

        return $preference->refresh();
    }
}
