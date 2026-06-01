<?php

use Database\Seeders\BillingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds default plans and required feature keys', function () {
    $this->seed(BillingSeeder::class);

    $slugs = DB::table('plans')->pluck('slug')->all();
    expect($slugs)->toContain('free', 'basic', 'pro', 'enterprise');

    $expectedFeatureKeys = [
        'chat.messages.daily',
        'chat.messages.monthly',
        'chat.conversations.active',
        'chat.webhook_endpoints.count',
        'chat.webhook_deliveries.monthly',
        'chat.attachments.monthly',
        'chat.attachments.storage_mb',
        'chat.history_retention_days',
        'chat.external_api.enabled',
        'chat.realtime.enabled',
        'chat.advanced_search.enabled',
        'chat.admin_reply.enabled',
        'dialer.calls.monthly',
        'dialer.concurrent_calls',
        'dialer.sip_accounts',
        'dialer.recordings.storage_mb',
        'dialer.recordings.retention_days',
        'dialer.webhook_endpoints.count',
        'dialer.webhook_deliveries.monthly',
        'dialer.analytics.enabled',
        'dialer.call_recording.enabled',
        'platform.api_tokens.count',
        'platform.activity_logs.retention_days',
        'platform.rate_limit.multiplier',
        'platform.monitoring.enabled',
    ];

    $actualKeys = DB::table('plan_features')->distinct()->pluck('feature_key')->all();
    foreach ($expectedFeatureKeys as $key) {
        expect($actualKeys)->toContain($key);
    }
});

it('can be run repeatedly without creating duplicate plan slugs or feature triples', function () {
    $this->seed(BillingSeeder::class);
    $this->seed(BillingSeeder::class);

    $planDuplicates = DB::table('plans')
        ->select('slug')
        ->groupBy('slug')
        ->havingRaw('COUNT(*) > 1')
        ->count();

    $featureDuplicates = DB::table('plan_features')
        ->select('plan_id', 'feature_key', 'period')
        ->groupBy('plan_id', 'feature_key', 'period')
        ->havingRaw('COUNT(*) > 1')
        ->count();

    expect($planDuplicates)->toBe(0);
    expect($featureDuplicates)->toBe(0);
});

