<?php

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\FeatureAccessService;
use App\Services\Billing\PlanService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gets default free plan', function () {
    $free = Plan::factory()->free()->create(['slug' => 'free']);

    expect(app(PlanService::class)->getDefaultFreePlan()?->is($free))->toBeTrue();
});

it('finds plan by slug and gets active public plans', function () {
    $basic = Plan::factory()->basic()->create(['slug' => 'basic']);
    Plan::factory()->enterprise()->create(['slug' => 'enterprise', 'is_public' => false]);
    Plan::factory()->pro()->create(['slug' => 'pro-disabled', 'is_active' => false]);

    $service = app(PlanService::class);

    expect($service->findBySlug('basic')?->is($basic))->toBeTrue();
    expect($service->getActivePublicPlans()->pluck('slug')->all())->toBe(['basic']);
});

it('gets feature value from plan features', function () {
    $plan = Plan::factory()->create();
    PlanFeature::factory()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'chat.messages.monthly',
        'value' => '2500',
        'value_type' => 'integer',
        'period' => 'monthly',
    ]);

    expect(app(PlanService::class)->getFeatureValue($plan, 'chat.messages.monthly'))->toBe(2500);
});

it('returns no current subscription for user without subscription', function () {
    $user = User::factory()->create();

    expect(app(SubscriptionService::class)->getCurrentSubscription($user))->toBeNull();
});

it('returns effective free plan for user without subscription', function () {
    $user = User::factory()->create();
    $free = Plan::factory()->free()->create(['slug' => 'free']);

    expect(app(SubscriptionService::class)->getEffectivePlan($user)?->is($free))->toBeTrue();
});

it('returns current plan for active subscription', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->pro()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $service = app(SubscriptionService::class);

    expect($service->hasActiveSubscription($user))->toBeTrue();
    expect($service->getCurrentPlan($user)?->is($plan))->toBeTrue();
});

it('counts trialing subscription as active', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'trialing',
    ]);

    expect(app(SubscriptionService::class)->hasActiveSubscription($user))->toBeTrue();
});

it('does not count pending cancelled or expired subscriptions as active', function (string $status) {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => $status,
    ]);

    expect(app(SubscriptionService::class)->hasActiveSubscription($user))->toBeFalse();
})->with(['pending', 'cancelled', 'expired']);

it('allows enabled boolean feature access', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->free()->create(['slug' => 'free']);
    PlanFeature::factory()->boolean()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'chat.realtime.enabled',
        'value' => '1',
    ]);

    $result = app(FeatureAccessService::class)->checkFeatureAvailability($user, 'chat.realtime.enabled');

    expect($result['allowed'])->toBeTrue();
    expect($result['reason'])->toBeNull();
    expect($result['plan_slug'])->toBe('free');
});

it('denies disabled boolean feature access', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->free()->create(['slug' => 'free']);
    PlanFeature::factory()->boolean()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'chat.external_api.enabled',
        'value' => '0',
    ]);

    $result = app(FeatureAccessService::class)->checkFeatureAvailability($user, 'chat.external_api.enabled');

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('feature_disabled');
});

it('denies missing feature access', function () {
    $user = User::factory()->create();
    Plan::factory()->free()->create(['slug' => 'free']);

    $result = app(FeatureAccessService::class)->checkFeatureAvailability($user, 'chat.advanced_search.enabled');

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('feature_not_available');
});

it('uses effective free plan for feature access when user has no subscription', function () {
    $user = User::factory()->create();
    $free = Plan::factory()->free()->create(['slug' => 'free']);
    PlanFeature::factory()->create([
        'plan_id' => $free->id,
        'feature_key' => 'chat.messages.monthly',
        'value' => '500',
        'value_type' => 'integer',
        'period' => 'monthly',
    ]);

    $result = app(FeatureAccessService::class)->checkFeatureAvailability($user, 'chat.messages.monthly');

    expect($result['allowed'])->toBeTrue();
    expect($result['plan_slug'])->toBe('free');
    expect($result['value'])->toBe(500);
});

it('active paid subscription overrides free plan for feature access', function () {
    $user = User::factory()->create();
    $free = Plan::factory()->free()->create(['slug' => 'free']);
    $pro = Plan::factory()->pro()->create(['slug' => 'pro']);
    PlanFeature::factory()->boolean()->create([
        'plan_id' => $free->id,
        'feature_key' => 'chat.advanced_search.enabled',
        'value' => '0',
    ]);
    PlanFeature::factory()->boolean()->create([
        'plan_id' => $pro->id,
        'feature_key' => 'chat.advanced_search.enabled',
        'value' => '1',
    ]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $pro->id,
        'status' => 'active',
    ]);

    $result = app(FeatureAccessService::class)->checkFeatureAvailability($user, 'chat.advanced_search.enabled');

    expect($result['allowed'])->toBeTrue();
    expect($result['plan_slug'])->toBe('pro');
});
