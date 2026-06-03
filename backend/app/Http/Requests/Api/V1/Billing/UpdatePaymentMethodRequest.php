<?php

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePaymentMethodRequest extends FormRequest
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
            'display_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'revoked'])],
            'metadata' => ['sometimes', 'nullable', 'array'],
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
