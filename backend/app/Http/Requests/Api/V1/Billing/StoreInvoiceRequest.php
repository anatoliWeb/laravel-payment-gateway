<?php

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:255'],
            'due_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['nullable', 'string', 'max:64'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_amount' => ['required', 'integer', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'integer', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'integer', 'min:0'],
            'items.*.metadata' => ['nullable', 'array'],
        ];
    }
}
