<?php

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'plan_slug' => ['nullable', 'string', 'max:120'],
            'payment_source' => ['nullable', 'in:wallet,payment_method,wallet_first'],
            'payment_strategy' => ['nullable', 'in:wallet_only,payment_method_only,wallet_first,manual_invoice'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'callback_url' => ['nullable', 'url', 'max:2048'],
            'auto_renew' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('plan_id') && ! $this->filled('plan_slug')) {
                $validator->errors()->add('plan_id', 'A plan_id or plan_slug is required.');
            }
        });
    }
}
