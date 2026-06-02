<?php

use App\Models\BillingRestriction;
use App\Models\FeatureOverride;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\FeatureAccessService;
use App\Services\Billing\FeatureOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('user-level override changes feature value', function () {
    $user = User::factory()->create();
    FeatureOverride::factory()->numericLimit(250)->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.messages.daily',
    ]);

    $value = app(FeatureOverrideService::class)->getOverrideValue($user, null, 'chat.messages.daily');

    expect($value)->toBe(250);
});

it('subscription-level override beats user-level override', function () {
    [$user, $subscription] = createOverrideSubscriptionContext();
    FeatureOverride::factory()->numericLimit(100)->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.messages.daily',
        'priority' => 999,
    ]);
    FeatureOverride::factory()->numericLimit(500)->subscriptionLevel()->create([
        'subscription_id' => $subscription->id,
        'feature_key' => 'chat.messages.daily',
        'priority' => 1,
    ]);

    $value = app(FeatureOverrideService::class)->getOverrideValue($user, $subscription, 'chat.messages.daily');

    expect($value)->toBe(500);
});

it('disabled override denies feature', function () {
    $user = User::factory()->create();
    Plan::factory()->free()->create(['slug' => 'free']);
    FeatureOverride::factory()->disabledFeature()->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.external_api.enabled',
    ]);

    $result = app(FeatureAccessService::class)->checkFeatureAvailability($user, 'chat.external_api.enabled');

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('feature_override_disabled');
});

it('expired override is ignored', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->free()->create(['slug' => 'free']);
    PlanFeature::factory()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'chat.messages.daily',
        'value' => '50',
        'value_type' => 'integer',
        'period' => 'daily',
    ]);
    FeatureOverride::factory()->numericLimit(500)->expired()->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.messages.daily',
    ]);

    $result = app(FeatureAccessService::class)->checkFeatureAvailability($user, 'chat.messages.daily');

    expect($result['allowed'])->toBeTrue();
    expect($result['value'])->toBe(50);
});

it('higher priority wins', function () {
    $user = User::factory()->create();
    FeatureOverride::factory()->numericLimit(100)->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.messages.daily',
        'priority' => 10,
    ]);
    FeatureOverride::factory()->numericLimit(300)->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.messages.daily',
        'priority' => 20,
    ]);

    $value = app(FeatureOverrideService::class)->getOverrideValue($user, null, 'chat.messages.daily');

    expect($value)->toBe(300);
});

it('newest wins when priority is equal', function () {
    $user = User::factory()->create();
    FeatureOverride::factory()->numericLimit(100)->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.messages.daily',
        'priority' => 10,
        'created_at' => now()->subMinute(),
    ]);
    FeatureOverride::factory()->numericLimit(200)->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.messages.daily',
        'priority' => 10,
        'created_at' => now(),
    ]);

    $value = app(FeatureOverrideService::class)->getOverrideValue($user, null, 'chat.messages.daily');

    expect($value)->toBe(200);
});

it('feature access service uses override before plan feature', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->free()->create(['slug' => 'free']);
    PlanFeature::factory()->boolean()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'chat.advanced_search.enabled',
        'value' => '0',
    ]);
    FeatureOverride::factory()->enabledFeature()->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.advanced_search.enabled',
    ]);

    $result = app(FeatureAccessService::class)->checkFeatureAvailability($user, 'chat.advanced_search.enabled');

    expect($result['allowed'])->toBeTrue();
    expect($result['value'])->toBeTrue();
});

it('restriction beats override', function () {
    $user = User::factory()->create();
    Plan::factory()->free()->create(['slug' => 'free']);
    FeatureOverride::factory()->enabledFeature()->create([
        'user_id' => $user->id,
        'feature_key' => 'chat.advanced_search.enabled',
    ]);
    BillingRestriction::factory()->featureBlocked('chat.advanced_search.enabled')->create([
        'user_id' => $user->id,
    ]);

    $result = app(FeatureAccessService::class)->checkFeatureAvailability($user, 'chat.advanced_search.enabled');

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('feature_blocked');
});

function createOverrideSubscriptionContext(): array
{
    $user = User::factory()->create();
    $plan = Plan::factory()->pro()->create();
    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    return [$user, $subscription, $plan];
}
