<?php

namespace Database\Seeders\Billing;

class BillingDemoFeatureOverrideSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $customerOne = $this->demoUser(self::CUSTOMER_ONE_EMAIL);
        $customerTwo = $this->demoUser(self::CUSTOMER_TWO_EMAIL);
        $normal = $this->demoUser(self::NORMAL_EMAIL);
        $admin = $this->demoUser(self::ADMIN_EMAIL);
        $customerOneSubscription = $customerOne->subscriptions()->where('uuid', 'demo-subscription-active')->firstOrFail();
        $trialingSubscription = $customerTwo->subscriptions()->where('uuid', 'demo-subscription-trialing')->firstOrFail();

        $this->upsertFeatureOverride($customerOne->id, null, 'chat.messages.daily', '5000', 'integer', 'daily', 'calendar_day', 100, 'Demo chat limit uplift', $admin->id, true);
        $this->upsertFeatureOverride($customerTwo->id, $trialingSubscription->id, 'dialer.concurrent_calls', '3', 'integer', 'none', 'none', 120, 'Demo subscription override', $admin->id, true);
        $this->upsertFeatureOverride($customerOne->id, $customerOneSubscription->id, 'platform.rate_limit.multiplier', '2.5', 'decimal', 'none', 'none', 90, 'Demo platform throughput override', $admin->id, true);
    }
}
