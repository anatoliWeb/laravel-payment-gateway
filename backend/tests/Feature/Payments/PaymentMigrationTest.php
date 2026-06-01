<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('verifies payment tables and important columns exist', function () {
    expect(Schema::hasTable('payments'))->toBeTrue();
    expect(Schema::hasTable('payment_transactions'))->toBeTrue();
    expect(Schema::hasTable('idempotency_keys'))->toBeTrue();
    expect(Schema::hasTable('webhook_deliveries'))->toBeTrue();

    foreach (['uuid', 'user_id', 'subscription_id', 'parent_payment_id', 'amount', 'status', 'provider', 'metadata'] as $column) {
        expect(Schema::hasColumn('payments', $column))->toBeTrue();
    }

    foreach (['payment_id', 'type', 'status_from', 'status_to', 'payload'] as $column) {
        expect(Schema::hasColumn('payment_transactions', $column))->toBeTrue();
    }

    foreach (['key', 'method', 'endpoint', 'request_hash', 'response_body', 'status'] as $column) {
        expect(Schema::hasColumn('idempotency_keys', $column))->toBeTrue();
    }

    foreach (['uuid', 'payment_id', 'subscription_id', 'event', 'status', 'payload', 'response_body', 'attempts', 'max_attempts'] as $column) {
        expect(Schema::hasColumn('webhook_deliveries', $column))->toBeTrue();
    }
});

it('verifies idempotency composite unique index exists', function () {
    $database = (string) DB::connection()->getDatabaseName();

    $uniqueExists = DB::table('information_schema.statistics')
        ->where('table_schema', $database)
        ->where('table_name', 'idempotency_keys')
        ->where('index_name', 'idempotency_keys_key_method_endpoint_uniq')
        ->where('non_unique', 0)
        ->exists();

    expect($uniqueExists)->toBeTrue();
});

