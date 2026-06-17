<?php

namespace Database\Seeders\Billing;

class BillingDemoSubscriptionSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $now = now();
        $customerOne = $this->demoUser(self::CUSTOMER_ONE_EMAIL);
        $customerTwo = $this->demoUser(self::CUSTOMER_TWO_EMAIL);
        $customerThree = $this->demoUser(self::CUSTOMER_THREE_EMAIL);
        $normal = $this->demoUser(self::NORMAL_EMAIL);

        $this->upsertSubscription([
            'uuid' => 'demo-subscription-active',
            'user_id' => $customerOne->id,
            'plan_id' => $this->demoPlan('pro')->id,
            'status' => 'active',
            'started_at' => $now->copy()->subDays(12),
            'current_period_start' => $now->copy()->startOfMonth(),
            'current_period_end' => $now->copy()->endOfMonth(),
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'cancel_at_period_end' => false,
            'ended_at' => null,
            'metadata' => [
                'purpose' => 'active_subscription_demo',
            ],
        ]);

        $this->upsertSubscription([
            'uuid' => 'demo-subscription-trialing',
            'user_id' => $customerTwo->id,
            'plan_id' => $this->demoPlan('basic')->id,
            'status' => 'trialing',
            'started_at' => $now->copy()->subDays(2),
            'current_period_start' => $now->copy()->subDays(2),
            'current_period_end' => $now->copy()->addDays(28),
            'trial_ends_at' => $now->copy()->addDays(12),
            'cancelled_at' => null,
            'cancel_at_period_end' => false,
            'ended_at' => null,
            'metadata' => [
                'purpose' => 'trialing_subscription_demo',
            ],
        ]);

        $this->upsertSubscription([
            'uuid' => 'demo-subscription-past-due',
            'user_id' => $customerThree->id,
            'plan_id' => $this->demoPlan('enterprise')->id,
            'status' => 'past_due',
            'started_at' => $now->copy()->subMonths(2),
            'current_period_start' => $now->copy()->subMonth(),
            'current_period_end' => $now->copy()->subDay(),
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'cancel_at_period_end' => false,
            'ended_at' => null,
            'metadata' => [
                'purpose' => 'past_due_subscription_demo',
            ],
        ]);

        $this->upsertSubscription([
            'uuid' => 'demo-subscription-cancelled',
            'user_id' => $normal->id,
            'plan_id' => $this->demoPlan('demo_enterprise')->id,
            'status' => 'cancelled',
            'started_at' => $now->copy()->subMonths(4),
            'current_period_start' => $now->copy()->subMonths(2),
            'current_period_end' => $now->copy()->subMonth(),
            'trial_ends_at' => null,
            'cancelled_at' => $now->copy()->subMonth(),
            'cancel_at_period_end' => true,
            'ended_at' => $now->copy()->subWeeks(3),
            'metadata' => [
                'purpose' => 'cancelled_subscription_demo',
            ],
        ]);
    }
}
