<?php

namespace Tests\Feature\Billing;

use App\Models\BillingRestriction;
use App\Models\FeatureOverride;
use App\Models\FeatureUsage;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\User;
use App\Services\Billing\BillingRestrictionService;
use App\Services\Billing\FeatureAccessService;
use App\Services\Billing\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FutureDialerBillingFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-15 10:30:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_feature_access_allows_enabled_dialer_boolean_feature(): void
    {
        $user = User::factory()->create();
        $this->createPlanFeature('dialer.analytics.enabled', '1', 'boolean', 'none');

        $result = app(FeatureAccessService::class)
            ->checkFeatureAvailability($user, 'dialer.analytics.enabled');

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['value']);
        $this->assertNull($result['reason']);
    }

    public function test_feature_access_denies_disabled_dialer_boolean_feature(): void
    {
        $user = User::factory()->create();
        $this->createPlanFeature('dialer.call_recording.enabled', '0', 'boolean', 'none');

        $result = app(FeatureAccessService::class)
            ->checkFeatureAvailability($user, 'dialer.call_recording.enabled');

        $this->assertFalse($result['allowed']);
        $this->assertFalse($result['value']);
        $this->assertSame('feature_disabled', $result['reason']);
    }

    public function test_usage_limit_allows_dialer_calls_under_monthly_limit(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlanFeature('dialer.calls.monthly', '100', 'integer', 'monthly');
        $this->createUsage($user, $plan, 'dialer.calls.monthly', 40, 100);

        $result = app(UsageLimitService::class)
            ->checkUsageLimit($user, 'dialer.calls.monthly', 10);

        $this->assertTrue($result['allowed']);
        $this->assertSame(40, $result['used']);
        $this->assertSame(100, $result['limit']);
        $this->assertSame('monthly', $result['period']);
    }

    public function test_usage_limit_denies_dialer_calls_over_monthly_limit(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlanFeature('dialer.calls.monthly', '100', 'integer', 'monthly');
        $this->createUsage($user, $plan, 'dialer.calls.monthly', 95, 100);

        $result = app(UsageLimitService::class)
            ->checkUsageLimit($user, 'dialer.calls.monthly', 10);

        $this->assertFalse($result['allowed']);
        $this->assertSame('limit_exceeded', $result['reason']);
    }

    public function test_feature_override_can_raise_dialer_call_limit(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlanFeature('dialer.calls.monthly', '100', 'integer', 'monthly');
        $this->createUsage($user, $plan, 'dialer.calls.monthly', 100, 100);

        FeatureOverride::factory()->numericLimit(250)->create([
            'user_id' => $user->id,
            'feature_key' => 'dialer.calls.monthly',
            'period' => 'monthly',
            'reset_policy' => 'calendar_month',
        ]);

        $result = app(UsageLimitService::class)
            ->checkUsageLimit($user, 'dialer.calls.monthly', 25);

        $this->assertTrue($result['allowed']);
        $this->assertSame(250, $result['limit']);
    }

    public function test_feature_restriction_can_block_one_dialer_feature_only(): void
    {
        $user = User::factory()->create();
        $this->createPlanFeature('dialer.calls.monthly', '100', 'integer', 'monthly');
        $this->createPlanFeature('dialer.analytics.enabled', '1', 'boolean', 'none');

        BillingRestriction::factory()->featureBlocked('dialer.calls.monthly')->create([
            'user_id' => $user->id,
        ]);

        $calls = app(UsageLimitService::class)
            ->checkUsageLimit($user, 'dialer.calls.monthly', 1);
        $analytics = app(FeatureAccessService::class)
            ->checkFeatureAvailability($user, 'dialer.analytics.enabled');

        $this->assertFalse($calls['allowed']);
        $this->assertSame('feature_blocked', $calls['reason']);
        $this->assertTrue($analytics['allowed']);
        $this->assertTrue(app(BillingRestrictionService::class)->isFeatureBlocked($user, 'dialer.calls.monthly'));
        $this->assertFalse(app(BillingRestrictionService::class)->isFeatureBlocked($user, 'dialer.analytics.enabled'));
    }

    private function createPlanFeature(string $featureKey, string $value, string $valueType, string $period): Plan
    {
        $plan = Plan::query()->where('slug', 'free')->first()
            ?? Plan::factory()->free()->create(['slug' => 'free']);

        PlanFeature::factory()->create([
            'plan_id' => $plan->id,
            'feature_key' => $featureKey,
            'value' => $value,
            'value_type' => $valueType,
            'period' => $period,
            'reset_policy' => match ($period) {
                'monthly' => 'calendar_month',
                default => 'none',
            },
        ]);

        return $plan;
    }

    private function createUsage(User $user, Plan $plan, string $featureKey, int $used, int $limit): FeatureUsage
    {
        return FeatureUsage::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => null,
            'plan_id' => $plan->id,
            'feature_key' => $featureKey,
            'period' => 'monthly',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'used' => $used,
            'limit_value' => $limit,
            'reset_at' => now()->addMonthNoOverflow()->startOfMonth(),
        ]);
    }
}
