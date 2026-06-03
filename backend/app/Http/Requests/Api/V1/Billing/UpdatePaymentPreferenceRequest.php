<?php

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy' => ['sometimes', 'string', Rule::in(['wallet_only', 'payment_method_only', 'wallet_first', 'manual_invoice'])],
            'default_payment_method_id' => ['sometimes', 'nullable', 'integer'],
            'auto_charge_enabled' => ['sometimes', 'boolean'],
            'auto_top_up_enabled' => ['sometimes', 'boolean'],
            'auto_top_up_threshold_amount' => ['sometimes', 'integer', 'min:0'],
            'auto_top_up_amount' => ['sometimes', 'integer', 'min:1'],
            'auto_top_up_currency' => ['sometimes', 'string', 'size:3'],
            'max_auto_top_up_per_day' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_auto_top_up_per_month' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
