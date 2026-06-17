<?php

namespace Database\Seeders\Billing;

use Database\Seeders\BillingSeeder;

class BillingDemoPlanSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $this->call(BillingSeeder::class);

        foreach (['free', 'basic', 'pro', 'enterprise', 'demo_enterprise'] as $slug) {
            $this->upsertPlanMetadata($slug, [
                'purpose' => 'demo_billing_catalog',
                'demo_ready' => true,
                'reporting_ready' => true,
                'portfolio_visible' => $slug !== 'free',
            ]);
        }

        $this->upsertPlanMetadata('demo_enterprise', [
            'demo_walkthrough' => true,
            'walkthrough_label' => 'portfolio_demo_enterprise',
        ]);
    }
}
