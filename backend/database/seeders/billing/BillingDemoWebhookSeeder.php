<?php

namespace Database\Seeders\Billing;

use App\Models\Payment;

class BillingDemoWebhookSeeder extends BillingDemoSeederSupport
{
    public function run(): void
    {
        $customerOne = $this->demoUser(self::CUSTOMER_ONE_EMAIL);
        $customerTwo = $this->demoUser(self::CUSTOMER_TWO_EMAIL);
        $customerThree = $this->demoUser(self::CUSTOMER_THREE_EMAIL);
        $normal = $this->demoUser(self::NORMAL_EMAIL);

        $succeeded = Payment::query()->where('uuid', 'demo-payment-succeeded')->firstOrFail();
        $pending = Payment::query()->where('uuid', 'demo-payment-pending')->firstOrFail();
        $failed = Payment::query()->where('uuid', 'demo-payment-failed')->firstOrFail();
        $expired = Payment::query()->where('uuid', 'demo-payment-expired')->firstOrFail();

        $this->upsertWebhookDelivery($succeeded->id, $succeeded->subscription_id, $succeeded->invoice_id, 'payment.succeeded', 'delivered', 1, 200, 'ok');
        $this->upsertWebhookDelivery($pending->id, $pending->subscription_id, $pending->invoice_id, 'payment.pending', 'retrying', 2, 500, 'temporary_failure');
        $this->upsertWebhookDelivery($failed->id, $failed->subscription_id, $failed->invoice_id, 'payment.failed', 'failed', 3, 500, 'gateway timeout');
        $this->upsertWebhookDelivery($expired->id, $expired->subscription_id, $expired->invoice_id, 'payment.expired', 'permanently_failed', 5, 410, 'expired');
    }
}
