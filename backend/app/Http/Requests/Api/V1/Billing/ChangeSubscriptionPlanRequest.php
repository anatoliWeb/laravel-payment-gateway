<?php

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;

class ChangeSubscriptionPlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'direction' => ['nullable', 'in:upgrade,downgrade'],
            'payment_source' => ['nullable', 'in:wallet,payment_method,wallet_first'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
