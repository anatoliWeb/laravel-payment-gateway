<?php

use App\Models\BillingRestriction;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\User;
use App\Services\Billing\BillingRestrictionService;
use App\Services\Billing\FeatureAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('active billing blacklist blocks feature access', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->free()->create(['slug' => 'free']);
    PlanFeature::factory()->boolean()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'chat.realtime.enabled',
        'value' => '1',
    ]);
    BillingRestriction::factory()->billingBlocked()->create(['user_id' => $user->id]);

    $result = app(FeatureAccessService::class)->checkFeatureAvailability($user, 'chat.realtime.enabled');

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('billing_blocked');
});

it('active payment blacklist is detectable', function () {
    $user = User::factory()->create();
    BillingRestriction::factory()->paymentBlocked()->create(['user_id' => $user->id]);

    expect(app(BillingRestrictionService::class)->isPaymentBlocked($user))->toBeTrue();
    expect(app(BillingRestrictionService::class)->isBillingBlocked($user))->toBeFalse();
});

it('feature blacklist blocks only selected feature', function () {
    $user = User::factory()->create();
    BillingRestriction::factory()->featureBlocked('chat.messages.daily')->create(['user_id' => $user->id]);

    $service = app(BillingRestrictionService::class);

    expect($service->isFeatureBlocked($user, 'chat.messages.daily'))->toBeTrue();
    expect($service->isFeatureBlocked($user, 'chat.messages.monthly'))->toBeFalse();
});

it('inactive restriction does not block', function () {
    $user = User::factory()->create();
    BillingRestriction::factory()->billingBlocked()->inactive()->create(['user_id' => $user->id]);

    expect(app(BillingRestrictionService::class)->isBillingBlocked($user))->toBeFalse();
});

it('expired restriction does not block', function () {
    $user = User::factory()->create();
    BillingRestriction::factory()->billingBlocked()->expired()->create(['user_id' => $user->id]);

    expect(app(BillingRestrictionService::class)->isBillingBlocked($user))->toBeFalse();
});
