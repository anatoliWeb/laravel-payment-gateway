<?php

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_status' => ['nullable', 'string', Rule::in([
                'pending',
                'processing',
                'succeeded',
                'failed',
                'expired',
                'cancelled',
            ])],
            'invoice_status' => ['nullable', 'string', Rule::in([
                'draft',
                'issued',
                'payment_pending',
                'paid',
                'failed',
                'void',
                'overdue',
                'cancelled',
            ])],
            'subscription_status' => ['nullable', 'string', Rule::in([
                'pending',
                'active',
                'trialing',
                'past_due',
                'cancelled',
                'expired',
            ])],
            'wallet_status' => ['nullable', 'string', Rule::in([
                'active',
                'suspended',
                'closed',
            ])],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => $this->filled('currency')
                ? strtoupper(trim((string) $this->input('currency')))
                : null,
            'payment_status' => $this->filled('payment_status')
                ? trim((string) $this->input('payment_status'))
                : null,
            'invoice_status' => $this->filled('invoice_status')
                ? trim((string) $this->input('invoice_status'))
                : null,
            'subscription_status' => $this->filled('subscription_status')
                ? trim((string) $this->input('subscription_status'))
                : null,
            'wallet_status' => $this->filled('wallet_status')
                ? trim((string) $this->input('wallet_status'))
                : null,
        ]);
    }

    /**
     * Return the normalized filter payload used by the reports service.
     *
     * WHY:
     * Query-string filters should stay consistent across report endpoints so
     * the dashboard can reuse one request shape without frontend arithmetic.
     *
     * @return array<string, int|string|null>
     */
    public function reportFilters(): array
    {
        $validated = $this->validated();

        return [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'payment_status' => $validated['payment_status'] ?? null,
            'invoice_status' => $validated['invoice_status'] ?? null,
            'subscription_status' => $validated['subscription_status'] ?? null,
            'wallet_status' => $validated['wallet_status'] ?? null,
            'plan_id' => isset($validated['plan_id']) ? (int) $validated['plan_id'] : null,
            'company_id' => isset($validated['company_id']) ? (int) $validated['company_id'] : null,
            'seller_id' => isset($validated['seller_id']) ? (int) $validated['seller_id'] : null,
            'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
        ];
    }
}
