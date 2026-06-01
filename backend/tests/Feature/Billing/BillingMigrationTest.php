<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('verifies billing tables and important columns exist', function () {
    expect(Schema::hasTable('plans'))->toBeTrue();
    expect(Schema::hasTable('plan_features'))->toBeTrue();
    expect(Schema::hasTable('subscriptions'))->toBeTrue();
    expect(Schema::hasTable('feature_usages'))->toBeTrue();

    foreach (['uuid', 'slug', 'type', 'price_amount', 'billing_interval', 'metadata'] as $column) {
        expect(Schema::hasColumn('plans', $column))->toBeTrue();
    }

    foreach (['plan_id', 'feature_key', 'value', 'value_type', 'period', 'reset_policy'] as $column) {
        expect(Schema::hasColumn('plan_features', $column))->toBeTrue();
    }

    foreach (['uuid', 'user_id', 'plan_id', 'status', 'current_period_end', 'cancel_at_period_end'] as $column) {
        expect(Schema::hasColumn('subscriptions', $column))->toBeTrue();
    }

    foreach (['user_id', 'feature_key', 'period', 'used', 'limit_value', 'reset_at'] as $column) {
        expect(Schema::hasColumn('feature_usages', $column))->toBeTrue();
    }
});

it('verifies billing unique constraints exist where practical', function () {
    $database = (string) DB::connection()->getDatabaseName();

    $plansSlugUnique = DB::table('information_schema.statistics')
        ->where('table_schema', $database)
        ->where('table_name', 'plans')
        ->where('column_name', 'slug')
        ->where('non_unique', 0)
        ->exists();

    $planFeaturesCompositeUnique = DB::table('information_schema.statistics')
        ->where('table_schema', $database)
        ->where('table_name', 'plan_features')
        ->where('index_name', 'plan_features_plan_feature_period_uniq')
        ->where('non_unique', 0)
        ->exists();

    expect($plansSlugUnique)->toBeTrue();
    expect($planFeaturesCompositeUnique)->toBeTrue();
});

