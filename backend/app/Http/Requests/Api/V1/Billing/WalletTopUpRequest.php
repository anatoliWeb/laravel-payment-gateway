<?php

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WalletTopUpRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_method_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (trim((string) $this->header('Idempotency-Key')) === '') {
                $validator->errors()->add('Idempotency-Key', 'The Idempotency-Key header is required.');
            }

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
