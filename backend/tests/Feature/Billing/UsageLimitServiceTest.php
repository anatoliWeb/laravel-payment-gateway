<?php

use App\Models\FeatureUsage;
use App\Models\BillingRestriction;
use App\Models\FeatureOverride;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\User;
use App\Services\Billing\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 10:30:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows numeric limit when used plus amount is within limit', function () {
    [$user] = createUsageContext('chat.messages.monthly', 100, 'monthly');
    createUsageRow($user, 'chat.messages.monthly', 'monthly', 25, 100);

    $result = app(UsageLimitService::class)->checkUsageLimit($user, 'chat.messages.monthly', 50);

    expect($result['allowed'])->toBeTrue();
    expect($result['used'])->toBe(25);
    expect($result['limit'])->toBe(100);
    expect($result['remaining'])->toBe(75);
});

it('denies numeric limit when used plus amount exceeds limit', function () {
    [$user] = createUsageContext('chat.messages.monthly', 100, 'monthly');
    createUsageRow($user, 'chat.messages.monthly', 'monthly', 90, 100);

    $result = app(UsageLimitService::class)->checkUsageLimit($user, 'chat.messages.monthly', 20);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('limit_exceeded');
});

it('creates feature usage row when incrementing first usage', function () {
    [$user] = createUsageContext('chat.messages.daily', 10, 'daily');

    $usage = app(UsageLimitService::class)->incrementUsage($user, 'chat.messages.daily', 3);

    expect($usage->used)->toBe(3);
    expect($usage->limit_value)->toBe(10);
    expect($usage->period)->toBe('daily');
});

it('increments existing usage row', function () {
    [$user] = createUsageContext('chat.messages.monthly', 100, 'monthly');
    createUsageRow($user, 'chat.messages.monthly', 'monthly', 25, 100);

    $usage = app(UsageLimitService::class)->incrementUsage($user, 'chat.messages.monthly', 10);

    expect($usage->used)->toBe(35);
});

it('does not silently exceed limit during increment', function () {
    [$user] = createUsageContext('chat.messages.monthly', 100, 'monthly');
    createUsageRow($user, 'chat.messages.monthly', 'monthly', 95, 100);

    expect(fn () => app(UsageLimitService::class)->incrementUsage($user, 'chat.messages.monthly', 10))
        ->toThrow(RuntimeException::class, 'limit_exceeded');
});

it('resets matching usage rows by period', function () {
    [$user] = createUsageContext('chat.messages.monthly', 100, 'monthly');
    createUsageRow($user, 'chat.messages.monthly', 'monthly', 50, 100);
    createUsageRow($user, 'chat.messages.daily', 'daily', 5, 10);

    $affected = app(UsageLimitService::class)->resetUsageByPeriod('monthly');

    expect($affected)->toBe(1);
    expect(FeatureUsage::query()->where('feature_key', 'chat.messages.monthly')->value('used'))->toBe(0);
    expect(FeatureUsage::query()->where('feature_key', 'chat.messages.daily')->value('used'))->toBe(5);
});

it('denies missing feature usage checks', function () {
    $user = User::factory()->create();
    Plan::factory()->free()->create(['slug' => 'free']);

    $result = app(UsageLimitService::class)->checkUsageLimit($user, 'chat.messages.monthly', 1);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('feature_not_available');
});

it('uses override numeric limit before plan feature limit', function () {
    [$user] = createUsageContext('chat.messages.daily', 10, 'daily');
    FeatureOverride::factory()->numericLimit(100)->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.messages.daily',
    ]);

    $result = app(UsageLimitService::class)->checkUsageLimit($user, 'chat.messages.daily', 50);

    expect($result['allowed'])->toBeTrue();
    expect($result['limit'])->toBe(100);
});

it('feature restriction beats override numeric limit', function () {
    [$user] = createUsageContext('chat.messages.daily', 10, 'daily');
    FeatureOverride::factory()->numericLimit(100)->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.messages.daily',
    ]);
    BillingRestriction::factory()->featureBlocked('chat.messages.daily')->create([
        'user_id' => $user->id,
    ]);

    $result = app(UsageLimitService::class)->checkUsageLimit($user, 'chat.messages.daily', 1);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('feature_blocked');
});

it('creates stable daily and monthly usage windows', function (string $period, string $expectedStart, string $expectedEnd) {
    [$user] = createUsageContext("chat.messages.{$period}", 100, $period);

    $usage = app(UsageLimitService::class)->incrementUsage($user, "chat.messages.{$period}", 1);

    expect($usage->period_start->toDateTimeString())->toBe($expectedStart);
    expect($usage->period_end->toDateTimeString())->toBe($expectedEnd);
})->with([
    ['daily', '2026-06-15 00:00:00', '2026-06-15 23:59:59'],
    ['monthly', '2026-06-01 00:00:00', '2026-06-30 23:59:59'],
]);

function createUsageContext(string $featureKey, int $limit, string $period): array
{
    $user = User::factory()->create();
    $plan = Plan::factory()->free()->create(['slug' => 'free']);
    $resetPolicy = match ($period) {
        'daily' => 'calendar_day',
        'monthly' => 'calendar_month',
        default => 'none',
    };

    PlanFeature::factory()->create([
        'plan_id' => $plan->id,
        'feature_key' => $featureKey,
        'value' => (string) $limit,
        'value_type' => 'integer',
        'period' => $period,
        'reset_policy' => $resetPolicy,
    ]);

    return [$user, $plan];
}

function createUsageRow(User $user, string $featureKey, string $period, int $used, int $limit): FeatureUsage
{
    $periodStart = $period === 'daily'
        ? now()->startOfDay()
        : now()->startOfMonth();

    $periodEnd = $period === 'daily'
        ? now()->endOfDay()
        : now()->endOfMonth();

    return FeatureUsage::factory()->create([
        'user_id' => $user->id,
        'subscription_id' => null,
        'plan_id' => Plan::query()->where('slug', 'free')->value('id'),
        'feature_key' => $featureKey,
        'period' => $period,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'used' => $used,
        'limit_value' => $limit,
        'reset_at' => $period === 'daily'
            ? now()->addDay()->startOfDay()
            : now()->addMonthNoOverflow()->startOfMonth(),
    ]);
}
