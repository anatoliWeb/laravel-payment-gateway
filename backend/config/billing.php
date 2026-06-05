<?php

return [
    'providers' => [
        'default' => env('PAYMENT_PROVIDER', 'simulator'),
        'platform_provider' => env('PAYMENT_PLATFORM_PROVIDER', 'simulator'),
        'external_enabled' => env('PAYMENT_EXTERNAL_PROVIDERS_ENABLED', false),
        'simulator_default' => env('PAYMENT_SIMULATOR_DEFAULT_ENABLED', true),
        'timeout_seconds' => env('PAYMENT_PROVIDER_TIMEOUT_SECONDS', 10),
        'retry_attempts' => env('PAYMENT_PROVIDER_RETRY_ATTEMPTS', 2),

        'platform' => [
            'simulator' => [
                'enabled' => true,
                'mode' => 'test',
                'credentials' => [],
                'public_config' => ['simulator_safe' => true],
            ],
            'stripe' => [
                'enabled' => env('PAYMENT_EXTERNAL_PROVIDERS_ENABLED', false) && filled(env('STRIPE_SECRET_KEY')),
                'mode' => env('STRIPE_MODE', 'test'),
                'credentials' => [
                    'secret_key' => env('STRIPE_SECRET_KEY'),
                    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
                ],
                'public_config' => [],
            ],
            'liqpay' => [
                'enabled' => env('PAYMENT_EXTERNAL_PROVIDERS_ENABLED', false) && filled(env('LIQPAY_PRIVATE_KEY')),
                'mode' => env('LIQPAY_MODE', 'test'),
                'credentials' => [
                    'public_key' => env('LIQPAY_PUBLIC_KEY'),
                    'private_key' => env('LIQPAY_PRIVATE_KEY'),
                ],
                'public_config' => [],
            ],
            'wayforpay' => [
                'enabled' => env('PAYMENT_EXTERNAL_PROVIDERS_ENABLED', false) && filled(env('WAYFORPAY_SECRET_KEY')),
                'mode' => env('WAYFORPAY_MODE', 'test'),
                'credentials' => [
                    'merchant_account' => env('WAYFORPAY_MERCHANT_ACCOUNT'),
                    'secret_key' => env('WAYFORPAY_SECRET_KEY'),
                ],
                'public_config' => [],
            ],
        ],
    ],

    'webhooks' => [
        'secret' => env('BILLING_WEBHOOK_SECRET', env('APP_KEY')),
        'timeout_seconds' => env('BILLING_WEBHOOK_TIMEOUT_SECONDS', 5),
        'max_attempts' => env('BILLING_WEBHOOK_MAX_ATTEMPTS', 5),
        'response_body_limit' => env('BILLING_WEBHOOK_RESPONSE_BODY_LIMIT', 2000),
        'headers' => [
            'event' => 'X-Billing-Event',
            'delivery' => 'X-Billing-Delivery',
            'signature' => 'X-Billing-Signature',
            'timestamp' => 'X-Billing-Timestamp',
        ],
    ],
];
