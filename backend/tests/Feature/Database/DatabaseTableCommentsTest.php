<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('verifies project tables have non-empty mysql table comments', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Table comment assertions require MySQL information_schema.');
    }

    $schema = DB::connection()->getDatabaseName();

    $tables = [
        'activity_logs',
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'permissions',
        'permission_role',
        'permission_user',
        'personal_access_tokens',
        'role_user',
        'roles',
        'sessions',
        'system_settings',
        'system_translations',
        'user_denied_permissions',
        'users',
        'chat_moderation_logs',
        'chat_user_devices',
        'chat_webhook_deliveries',
        'chat_webhook_endpoints',
        'conversation_participants',
        'conversations',
        'external_message_mappings',
        'message_attachments',
        'message_deliveries',
        'message_device_reads',
        'message_reads',
        'messages',
        'notifications',
        'user_notification_preferences',
        'webhook_deliveries',
        'plans',
        'plan_features',
        'subscriptions',
        'feature_usages',
        'payments',
        'payment_transactions',
        'idempotency_keys',
    ];

    foreach ($tables as $table) {
        $comment = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', $table)
            ->value('TABLE_COMMENT');

        expect($comment)->not->toBeNull();
        expect(trim((string) $comment))->not->toBe('');
    }
});

it('verifies key table comments contain expected domain keywords', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Table comment assertions require MySQL information_schema.');
    }

    $schema = DB::connection()->getDatabaseName();

    $expectations = [
        'plans' => 'billing',
        'payments' => 'payment',
        'users' => 'user',
        'conversations' => 'chat',
        'permissions' => 'rbac',
    ];

    foreach ($expectations as $table => $keyword) {
        $comment = (string) DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', $table)
            ->value('TABLE_COMMENT');

        expect(strtolower($comment))->toContain($keyword);
    }
});

it('verifies key billing and payment columns have non-empty comments', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Column comment assertions require MySQL information_schema.');
    }

    $schema = DB::connection()->getDatabaseName();

    $columns = [
        ['plans', 'slug'],
        ['plans', 'price_amount'],
        ['plans', 'billing_interval'],
        ['plans', 'metadata'],
        ['plan_features', 'feature_key'],
        ['plan_features', 'value'],
        ['plan_features', 'value_type'],
        ['plan_features', 'period'],
        ['subscriptions', 'status'],
        ['subscriptions', 'current_period_start'],
        ['subscriptions', 'current_period_end'],
        ['subscriptions', 'cancel_at_period_end'],
        ['feature_usages', 'feature_key'],
        ['feature_usages', 'used'],
        ['feature_usages', 'limit_value'],
        ['feature_usages', 'reset_at'],
        ['payments', 'status'],
        ['payments', 'amount'],
        ['payments', 'payment_method'],
        ['payments', 'provider'],
        ['payments', 'provider_reference'],
        ['payments', 'failure_reason'],
        ['payments', 'callback_url'],
        ['payments', 'metadata'],
        ['payment_transactions', 'type'],
        ['payment_transactions', 'status_from'],
        ['payment_transactions', 'status_to'],
        ['payment_transactions', 'payload'],
        ['idempotency_keys', 'key'],
        ['idempotency_keys', 'request_hash'],
        ['idempotency_keys', 'status'],
        ['idempotency_keys', 'locked_until'],
        ['idempotency_keys', 'expires_at'],
        ['webhook_deliveries', 'event'],
        ['webhook_deliveries', 'status'],
        ['webhook_deliveries', 'payload'],
        ['webhook_deliveries', 'response_status'],
        ['webhook_deliveries', 'response_body'],
        ['webhook_deliveries', 'next_retry_at'],
    ];

    foreach ($columns as [$table, $column]) {
        $comment = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->value('COLUMN_COMMENT');

        expect($comment)->not->toBeNull();
        expect(trim((string) $comment))->not->toBe('');
    }
});

