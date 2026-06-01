<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id()->comment('Primary subscription ID.');
            $table->uuid('uuid')->unique()->comment('Public unique subscription identifier.');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('Subscription owner user.');
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete()->comment('Currently assigned billing plan.');
            $table->string('status', 32)->index()->comment('Current lifecycle state stored as string and later cast to PHP enum.');
            $table->timestamp('started_at')->nullable()->comment('Timestamp when subscription became active.');
            $table->timestamp('current_period_start')->nullable()->comment('Current billing period start timestamp.');
            $table->timestamp('current_period_end')->nullable()->index()->comment('Current billing period end timestamp.');
            $table->timestamp('trial_ends_at')->nullable()->comment('Trial expiration timestamp, if trialing.');
            $table->timestamp('cancelled_at')->nullable()->comment('Timestamp when cancellation was requested or executed.');
            $table->boolean('cancel_at_period_end')->default(false)->index()->comment('Whether cancellation should take effect at period end.');
            $table->timestamp('ended_at')->nullable()->comment('Timestamp when subscription access fully ended.');
            $table->json('metadata')->nullable()->comment('Safe extension metadata for non-core subscription context.');
            $table->timestamps();

            $table->index(['user_id', 'status'], 'subscriptions_user_status_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE subscriptions COMMENT = 'User billing subscriptions with lifecycle and period tracking.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

