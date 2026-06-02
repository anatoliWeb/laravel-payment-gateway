<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('verifies billing and payment table comments', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Table comment assertions require MySQL information_schema.');
    }

    $schema = DB::connection()->getDatabaseName();

    $expected = [
        'plans' => 'Billing catalog plans used for subscriptions and feature access decisions.',
        'plan_features' => 'Per-plan feature configuration and usage limits for billing modules.',
        'subscriptions' => 'User billing subscriptions with lifecycle and period tracking.',
        'feature_usages' => 'Feature usage ledger for enforcing plan limits across modules.',
        'payments' => 'Payment attempts with lifecycle timestamps and retry lineage.',
        'payment_transactions' => 'Append-only timeline of payment state transitions and side effects.',
        'idempotency_keys' => 'Idempotency request registry for safe payment create/retry operations.',
        'webhook_deliveries' => 'Outgoing billing webhook delivery attempts, retries, and responses.',
    ];

    foreach ($expected as $table => $comment) {
        $actual = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', $table)
            ->value('TABLE_COMMENT');

        expect($actual)->toBe($comment);
    }
});

it('verifies key column comments for billing and payment schema', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Column comment assertions require MySQL information_schema.');
    }

    $schema = DB::connection()->getDatabaseName();

    $columns = [
        ['plans', 'slug', 'Stable machine-readable plan key used by seeders, API contracts, and feature checks.'],
        ['plans', 'price_amount', 'Plan price stored in minor currency units.'],
        ['plan_features', 'feature_key', 'Stable feature key, e.g. chat.messages.monthly.'],
        ['subscriptions', 'status', 'Current lifecycle state stored as string and later cast to PHP enum.'],
        ['feature_usages', 'feature_key', 'Feature key this usage row belongs to.'],
        ['payments', 'status', 'Current payment lifecycle state stored as string for enum casting.'],
        ['payments', 'amount', 'Payment amount stored in minor currency units.'],
        ['payment_transactions', 'type', 'Transaction event type, e.g. payment_succeeded.'],
        ['idempotency_keys', 'key', 'Client-provided idempotency key.'],
        ['webhook_deliveries', 'status', 'Delivery lifecycle status stored as string for enum casting.'],
        ['webhook_deliveries', 'event', 'Webhook event name, e.g. payment.succeeded.'],
    ];

    foreach ($columns as [$table, $column, $comment]) {
        $actual = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->value('COLUMN_COMMENT');

        expect($actual)->toBe($comment);
    }
});

