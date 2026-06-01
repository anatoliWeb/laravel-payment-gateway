<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id()->comment('Primary webhook delivery record ID.');
            $table->uuid('uuid')->unique()->comment('Public unique webhook delivery identifier.');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete()->comment('Optional related payment attempt.');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete()->comment('Optional related subscription.');
            $table->unsignedBigInteger('invoice_id')->nullable()->comment('Future invoice relation placeholder; no FK until invoice module exists.');
            $table->string('event', 128)->index()->comment('Webhook event name, e.g. payment.succeeded.');
            $table->string('url', 2048)->comment('Destination callback URL used for this delivery.');
            $table->string('status', 32)->index()->comment('Delivery lifecycle status stored as string for enum casting.');
            $table->json('payload')->comment('Webhook payload body prepared for delivery.');
            $table->unsignedSmallInteger('response_status')->nullable()->comment('HTTP status code returned by receiver.');
            $table->text('response_body')->nullable()->comment('Sanitized/truncated response body from receiver.');
            $table->unsignedInteger('attempts')->default(0)->comment('Total number of send attempts already made.');
            $table->unsignedInteger('max_attempts')->default(5)->comment('Configured maximum attempts before permanent failure.');
            $table->timestamp('next_retry_at')->nullable()->comment('Timestamp when next retry should be attempted.');
            $table->timestamp('last_attempt_at')->nullable()->comment('Timestamp of the latest delivery attempt.');
            $table->timestamp('delivered_at')->nullable()->index()->comment('Timestamp when delivery succeeded.');
            $table->timestamp('failed_at')->nullable()->index()->comment('Timestamp when delivery entered failed state.');
            $table->json('metadata')->nullable()->comment('Safe extension metadata for retry/debug context.');
            $table->timestamps();

            $table->index(['status', 'next_retry_at'], 'webhook_deliveries_status_retry_idx');
            $table->index(['payment_id'], 'webhook_deliveries_payment_idx');
            $table->index(['subscription_id'], 'webhook_deliveries_subscription_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE webhook_deliveries COMMENT = 'Outgoing billing webhook delivery attempts, retries, and responses.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};

