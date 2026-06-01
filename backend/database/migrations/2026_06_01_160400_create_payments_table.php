<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id()->comment('Primary payment attempt ID.');
            $table->uuid('uuid')->unique()->comment('Public unique payment identifier.');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('Owner user who initiated this payment attempt.');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete()->comment('Optional linked subscription context.');
            $table->unsignedBigInteger('invoice_id')->nullable()->comment('Future invoice relation placeholder; no FK until invoice module exists.');
            $table->foreignId('parent_payment_id')->nullable()->constrained('payments')->nullOnDelete()->comment('Previous payment attempt in retry chain.');
            $table->unsignedBigInteger('amount')->comment('Payment amount stored in minor currency units.');
            $table->string('currency', 3)->comment('ISO-like 3-letter transaction currency.');
            $table->string('status', 32)->index()->comment('Current payment lifecycle state stored as string for enum casting.');
            $table->string('payment_method', 64)->comment('Simulated payment method code.');
            $table->string('provider', 64)->default('simulator')->comment('Payment provider identifier; simulator by default.');
            $table->string('provider_reference')->nullable()->index()->comment('External/provider correlation reference when available.');
            $table->string('description')->nullable()->comment('Optional payment purpose summary.');
            $table->string('failure_reason', 64)->nullable()->comment('Safe machine-readable failure reason.');
            $table->string('callback_url', 2048)->nullable()->comment('Optional callback URL for async delivery context.');
            $table->json('metadata')->nullable()->comment('Safe extension payload; must not contain secrets or raw payment data.');
            $table->timestamp('paid_at')->nullable()->comment('Timestamp when payment became succeeded.');
            $table->timestamp('failed_at')->nullable()->comment('Timestamp when payment became failed.');
            $table->timestamp('expired_at')->nullable()->comment('Timestamp when payment expired.');
            $table->timestamp('cancelled_at')->nullable()->comment('Timestamp when payment was cancelled.');
            $table->timestamps();

            $table->index(['user_id', 'status'], 'payments_user_status_idx');
            $table->index(['subscription_id'], 'payments_subscription_idx');
            $table->index(['parent_payment_id'], 'payments_parent_idx');
            $table->index(['status', 'created_at'], 'payments_status_created_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments COMMENT = 'Payment attempts with lifecycle timestamps and retry lineage.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

