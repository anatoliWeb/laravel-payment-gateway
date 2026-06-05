<?php

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_source' => ['nullable', 'string', Rule::in(['wallet', 'payment_method', 'wallet_first'])],
            'payment_strategy' => ['nullable', 'string', Rule::in(['wallet_only', 'payment_method_only', 'wallet_first', 'manual_invoice'])],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'callback_url' => ['nullable', 'url'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
