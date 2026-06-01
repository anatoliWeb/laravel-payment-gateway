<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Entry plan for onboarding and product evaluation.',
                'type' => 'free',
                'price_amount' => 0,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'trial_days' => 0,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 10,
                'metadata' => json_encode(['seeded_by' => 'BillingSeeder']),
            ],
            [
                'slug' => 'basic',
                'name' => 'Basic',
                'description' => 'Paid starter plan for small teams and API access.',
                'type' => 'paid',
                'price_amount' => 2900,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'trial_days' => 0,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 20,
                'metadata' => json_encode(['seeded_by' => 'BillingSeeder']),
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'Production-oriented paid plan with higher limits.',
                'type' => 'paid',
                'price_amount' => 9900,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'trial_days' => 0,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 30,
                'metadata' => json_encode(['seeded_by' => 'BillingSeeder']),
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'High-scale plan for advanced operational needs.',
                'type' => 'enterprise',
                'price_amount' => 29900,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'trial_days' => 0,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 40,
                'metadata' => json_encode(['seeded_by' => 'BillingSeeder']),
            ],
            [
                'slug' => 'demo_enterprise',
                'name' => 'Demo Enterprise',
                'description' => 'Portfolio/demo-only plan with very high limits.',
                'type' => 'demo',
                'price_amount' => 0,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'trial_days' => 0,
                'is_active' => true,
                'is_public' => false,
                'sort_order' => 50,
                'metadata' => json_encode(['seeded_by' => 'BillingSeeder', 'demo' => true]),
            ],
        ];

        $planRows = array_map(function (array $plan) use ($now): array {
            return array_merge($plan, [
                'uuid' => (string) Str::uuid(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $plans);

        // WHY:
        // We upsert by slug to keep seeding idempotent and stable across environments.
        DB::table('plans')->upsert(
            $planRows,
            ['slug'],
            ['name', 'description', 'type', 'price_amount', 'currency', 'billing_interval', 'trial_days', 'is_active', 'is_public', 'sort_order', 'metadata', 'updated_at']
        );

        $planIdBySlug = DB::table('plans')
            ->whereIn('slug', array_column($plans, 'slug'))
            ->pluck('id', 'slug')
            ->all();

        $featureTemplates = [
            ['feature_key' => 'chat.messages.daily', 'value_type' => 'integer', 'period' => 'daily', 'reset_policy' => 'calendar_day'],
            ['feature_key' => 'chat.messages.monthly', 'value_type' => 'integer', 'period' => 'monthly', 'reset_policy' => 'calendar_month'],
            ['feature_key' => 'chat.conversations.active', 'value_type' => 'integer', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'chat.webhook_endpoints.count', 'value_type' => 'integer', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'chat.webhook_deliveries.monthly', 'value_type' => 'integer', 'period' => 'monthly', 'reset_policy' => 'calendar_month'],
            ['feature_key' => 'chat.attachments.monthly', 'value_type' => 'integer', 'period' => 'monthly', 'reset_policy' => 'calendar_month'],
            ['feature_key' => 'chat.attachments.storage_mb', 'value_type' => 'integer', 'period' => 'monthly', 'reset_policy' => 'calendar_month'],
            ['feature_key' => 'chat.history_retention_days', 'value_type' => 'integer', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'chat.external_api.enabled', 'value_type' => 'boolean', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'chat.realtime.enabled', 'value_type' => 'boolean', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'chat.advanced_search.enabled', 'value_type' => 'boolean', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'chat.admin_reply.enabled', 'value_type' => 'boolean', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'dialer.calls.monthly', 'value_type' => 'integer', 'period' => 'monthly', 'reset_policy' => 'calendar_month'],
            ['feature_key' => 'dialer.concurrent_calls', 'value_type' => 'integer', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'dialer.sip_accounts', 'value_type' => 'integer', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'dialer.recordings.storage_mb', 'value_type' => 'integer', 'period' => 'monthly', 'reset_policy' => 'calendar_month'],
            ['feature_key' => 'dialer.recordings.retention_days', 'value_type' => 'integer', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'dialer.webhook_endpoints.count', 'value_type' => 'integer', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'dialer.webhook_deliveries.monthly', 'value_type' => 'integer', 'period' => 'monthly', 'reset_policy' => 'calendar_month'],
            ['feature_key' => 'dialer.analytics.enabled', 'value_type' => 'boolean', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'dialer.call_recording.enabled', 'value_type' => 'boolean', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'platform.api_tokens.count', 'value_type' => 'integer', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'platform.activity_logs.retention_days', 'value_type' => 'integer', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'platform.rate_limit.multiplier', 'value_type' => 'decimal', 'period' => 'none', 'reset_policy' => 'none'],
            ['feature_key' => 'platform.monitoring.enabled', 'value_type' => 'boolean', 'period' => 'none', 'reset_policy' => 'none'],
        ];

        $valuesByPlan = [
            'free' => [
                'chat.messages.daily' => '50',
                'chat.messages.monthly' => '500',
                'chat.conversations.active' => '3',
                'chat.webhook_endpoints.count' => '0',
                'chat.webhook_deliveries.monthly' => '0',
                'chat.attachments.monthly' => '10',
                'chat.attachments.storage_mb' => '128',
                'chat.history_retention_days' => '7',
                'chat.external_api.enabled' => '0',
                'chat.realtime.enabled' => '1',
                'chat.advanced_search.enabled' => '0',
                'chat.admin_reply.enabled' => '0',
                'dialer.calls.monthly' => '0',
                'dialer.concurrent_calls' => '0',
                'dialer.sip_accounts' => '0',
                'dialer.recordings.storage_mb' => '0',
                'dialer.recordings.retention_days' => '0',
                'dialer.webhook_endpoints.count' => '0',
                'dialer.webhook_deliveries.monthly' => '0',
                'dialer.analytics.enabled' => '0',
                'dialer.call_recording.enabled' => '0',
                'platform.api_tokens.count' => '5',
                'platform.activity_logs.retention_days' => '14',
                'platform.rate_limit.multiplier' => '1.0',
                'platform.monitoring.enabled' => '0',
            ],
            'basic' => [
                'chat.messages.daily' => '500',
                'chat.messages.monthly' => '10000',
                'chat.conversations.active' => '25',
                'chat.webhook_endpoints.count' => '3',
                'chat.webhook_deliveries.monthly' => '5000',
                'chat.attachments.monthly' => '250',
                'chat.attachments.storage_mb' => '2048',
                'chat.history_retention_days' => '30',
                'chat.external_api.enabled' => '1',
                'chat.realtime.enabled' => '1',
                'chat.advanced_search.enabled' => '0',
                'chat.admin_reply.enabled' => '1',
                'dialer.calls.monthly' => '100',
                'dialer.concurrent_calls' => '1',
                'dialer.sip_accounts' => '1',
                'dialer.recordings.storage_mb' => '512',
                'dialer.recordings.retention_days' => '14',
                'dialer.webhook_endpoints.count' => '1',
                'dialer.webhook_deliveries.monthly' => '500',
                'dialer.analytics.enabled' => '0',
                'dialer.call_recording.enabled' => '0',
                'platform.api_tokens.count' => '20',
                'platform.activity_logs.retention_days' => '30',
                'platform.rate_limit.multiplier' => '1.5',
                'platform.monitoring.enabled' => '0',
            ],
            'pro' => [
                'chat.messages.daily' => '5000',
                'chat.messages.monthly' => '100000',
                'chat.conversations.active' => '250',
                'chat.webhook_endpoints.count' => '10',
                'chat.webhook_deliveries.monthly' => '50000',
                'chat.attachments.monthly' => '2500',
                'chat.attachments.storage_mb' => '10240',
                'chat.history_retention_days' => '90',
                'chat.external_api.enabled' => '1',
                'chat.realtime.enabled' => '1',
                'chat.advanced_search.enabled' => '1',
                'chat.admin_reply.enabled' => '1',
                'dialer.calls.monthly' => '1000',
                'dialer.concurrent_calls' => '2',
                'dialer.sip_accounts' => '2',
                'dialer.recordings.storage_mb' => '4096',
                'dialer.recordings.retention_days' => '30',
                'dialer.webhook_endpoints.count' => '3',
                'dialer.webhook_deliveries.monthly' => '5000',
                'dialer.analytics.enabled' => '1',
                'dialer.call_recording.enabled' => '1',
                'platform.api_tokens.count' => '50',
                'platform.activity_logs.retention_days' => '90',
                'platform.rate_limit.multiplier' => '2.0',
                'platform.monitoring.enabled' => '1',
            ],
            'enterprise' => [
                'chat.messages.daily' => '20000',
                'chat.messages.monthly' => '500000',
                'chat.conversations.active' => '1000',
                'chat.webhook_endpoints.count' => '50',
                'chat.webhook_deliveries.monthly' => '300000',
                'chat.attachments.monthly' => '10000',
                'chat.attachments.storage_mb' => '51200',
                'chat.history_retention_days' => '365',
                'chat.external_api.enabled' => '1',
                'chat.realtime.enabled' => '1',
                'chat.advanced_search.enabled' => '1',
                'chat.admin_reply.enabled' => '1',
                'dialer.calls.monthly' => '10000',
                'dialer.concurrent_calls' => '10',
                'dialer.sip_accounts' => '10',
                'dialer.recordings.storage_mb' => '51200',
                'dialer.recordings.retention_days' => '90',
                'dialer.webhook_endpoints.count' => '20',
                'dialer.webhook_deliveries.monthly' => '100000',
                'dialer.analytics.enabled' => '1',
                'dialer.call_recording.enabled' => '1',
                'platform.api_tokens.count' => '200',
                'platform.activity_logs.retention_days' => '365',
                'platform.rate_limit.multiplier' => '3.0',
                'platform.monitoring.enabled' => '1',
            ],
            'demo_enterprise' => [
                'chat.messages.daily' => '999999',
                'chat.messages.monthly' => '9999999',
                'chat.conversations.active' => '9999',
                'chat.webhook_endpoints.count' => '99',
                'chat.webhook_deliveries.monthly' => '999999',
                'chat.attachments.monthly' => '99999',
                'chat.attachments.storage_mb' => '102400',
                'chat.history_retention_days' => '365',
                'chat.external_api.enabled' => '1',
                'chat.realtime.enabled' => '1',
                'chat.advanced_search.enabled' => '1',
                'chat.admin_reply.enabled' => '1',
                'dialer.calls.monthly' => '999999',
                'dialer.concurrent_calls' => '20',
                'dialer.sip_accounts' => '20',
                'dialer.recordings.storage_mb' => '102400',
                'dialer.recordings.retention_days' => '180',
                'dialer.webhook_endpoints.count' => '50',
                'dialer.webhook_deliveries.monthly' => '999999',
                'dialer.analytics.enabled' => '1',
                'dialer.call_recording.enabled' => '1',
                'platform.api_tokens.count' => '500',
                'platform.activity_logs.retention_days' => '365',
                'platform.rate_limit.multiplier' => '5.0',
                'platform.monitoring.enabled' => '1',
            ],
        ];

        $planFeatureRows = [];
        foreach ($valuesByPlan as $slug => $featureValues) {
            $planId = $planIdBySlug[$slug] ?? null;
            if (! $planId) {
                continue;
            }

            foreach ($featureTemplates as $template) {
                $featureKey = $template['feature_key'];
                $planFeatureRows[] = [
                    'plan_id' => $planId,
                    'feature_key' => $featureKey,
                    'value' => $featureValues[$featureKey] ?? '0',
                    'value_type' => $template['value_type'],
                    'period' => $template['period'],
                    'reset_policy' => $template['reset_policy'],
                    'is_enabled' => true,
                    'metadata' => json_encode(['seeded_by' => 'BillingSeeder']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('plan_features')->upsert(
            $planFeatureRows,
            ['plan_id', 'feature_key', 'period'],
            ['value', 'value_type', 'reset_policy', 'is_enabled', 'metadata', 'updated_at']
        );
    }
}

