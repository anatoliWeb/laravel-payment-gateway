<?php

use App\Models\FeatureUsage;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('creates plan with fillable fields, casts and relations', function () {
    $plan = Plan::factory()
        ->basic()
        ->create([
            'slug' => 'basic-model-test',
            'metadata' => ['level' => 'basic'],
            'is_active' => 1,
            'is_public' => 1,
            'price_amount' => '2900',
            'trial_days' => '7',
            'sort_order' => '20',
        ]);

    $feature = PlanFeature::factory()->create(['plan_id' => $plan->id]);
    $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);

    expect($plan->metadata)->toBe(['level' => 'basic']);
    expect($plan->is_active)->toBeTrue();
    expect($plan->is_public)->toBeTrue();
    expect($plan->price_amount)->toBe(2900);
    expect($plan->trial_days)->toBe(7);
    expect($plan->sort_order)->toBe(20);
    expect($plan->features()->first()->is($feature))->toBeTrue();
    expect($plan->subscriptions()->first()->is($subscription))->toBeTrue();
    expect(Plan::active()->bySlug('basic-model-test')->exists())->toBeTrue();
    expect(Plan::public()->bySlug('basic-model-test')->exists())->toBeTrue();
});

it('creates plan feature with casts and belongs to plan relation', function () {
    $plan = Plan::factory()->create();
    $feature = PlanFeature::factory()
        ->boolean()
        ->create([
            'plan_id' => $plan->id,
            'metadata' => ['flag' => 'api'],
            'is_enabled' => 1,
        ]);

    expect($feature->metadata)->toBe(['flag' => 'api']);
    expect($feature->is_enabled)->toBeTrue();
    expect($feature->plan->is($plan))->toBeTrue();
});

it('creates subscription with date casts and user and plan relations', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $subscription = Subscription::factory()
        ->cancelledAtPeriodEnd()
        ->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'metadata' => ['source' => 'model-test'],
        ]);

    expect($subscription->started_at)->toBeInstanceOf(Carbon::class);
    expect($subscription->current_period_start)->toBeInstanceOf(Carbon::class);
    expect($subscription->current_period_end)->toBeInstanceOf(Carbon::class);
    expect($subscription->cancelled_at)->toBeInstanceOf(Carbon::class);
    expect($subscription->cancel_at_period_end)->toBeTrue();
    expect($subscription->metadata)->toBe(['source' => 'model-test']);
    expect($subscription->user->is($user))->toBeTrue();
    expect($subscription->plan->is($plan))->toBeTrue();
    expect($user->subscriptions()->first()->is($subscription))->toBeTrue();
});

it('creates feature usage with casts and billing relations', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
    ]);

    $usage = FeatureUsage::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'subscription_id' => $subscription->id,
        'used' => '25',
        'limit_value' => '1000',
        'metadata' => ['window' => 'monthly'],
    ]);

    expect($usage->period_start)->toBeInstanceOf(Carbon::class);
    expect($usage->period_end)->toBeInstanceOf(Carbon::class);
    expect($usage->reset_at)->toBeInstanceOf(Carbon::class);
    expect($usage->used)->toBe(25);
    expect($usage->limit_value)->toBe(1000);
    expect($usage->metadata)->toBe(['window' => 'monthly']);
    expect($usage->user->is($user))->toBeTrue();
    expect($usage->plan->is($plan))->toBeTrue();
    expect($usage->subscription->is($subscription))->toBeTrue();
});

