<?php

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentMethodRequest extends FormRequest
{
    private const FORBIDDEN_KEYS = [
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

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['fake_card', 'fake_manual_invoice', 'fake_wallet'])],
            'brand' => ['nullable', 'string', 'max:50'],
            'last4' => ['nullable', 'string', 'size:4'],
            'exp_month' => ['nullable', 'integer', 'between:1,12'],
            'exp_year' => ['nullable', 'integer', 'min:2024', 'max:2100'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (self::FORBIDDEN_KEYS as $key) {
                if ($this->has($key)) {
                    $validator->errors()->add($key, 'Raw payment data is not allowed.');
                }
            }

            foreach ($this->unsafeMetadataKeys((array) $this->input('metadata', [])) as $key) {
                $validator->errors()->add("metadata.{$key}", 'Unsafe metadata keys are not allowed.');
            }
        });
    }

    private function unsafeMetadataKeys(array $metadata, string $prefix = ''): array
    {
        $unsafe = [];

        foreach ($metadata as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $path = $prefix === '' ? $normalizedKey : "{$prefix}.{$normalizedKey}";

            if (in_array($normalizedKey, self::FORBIDDEN_KEYS, true)) {
                $unsafe[] = $path;
            }

            if (is_array($value)) {
                $unsafe = array_merge($unsafe, $this->unsafeMetadataKeys($value, $path));
            }
        }

        return $unsafe;
    }
}
