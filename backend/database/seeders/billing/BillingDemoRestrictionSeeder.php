<?php

namespace Database\Seeders\Billing;

class BillingDemoRestrictionSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $customerOne = $this->demoUser(self::CUSTOMER_ONE_EMAIL);
        $customerThree = $this->demoUser(self::CUSTOMER_THREE_EMAIL);
        $normal = $this->demoUser(self::NORMAL_EMAIL);
        $admin = $this->demoUser(self::ADMIN_EMAIL);

        $this->upsertRestriction($customerOne->id, 'billing_blocked', 'billing', null, 'Manual billing review demo', $admin->id, true);
        $this->upsertRestriction($customerThree->id, 'payment_blocked', 'payment', null, 'Payment blocked for demo review', $admin->id, true);
        $this->upsertRestriction($normal->id, 'feature_blocked', 'feature', 'chat.messages.daily', 'Temporary feature block demo', $admin->id, true);
    }
}
