<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_usages', function (Blueprint $table) {
            $table->id()->comment('Primary usage ledger ID.');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('Owner user whose feature usage is tracked.');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete()->comment('Optional linked subscription snapshot.');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete()->comment('Optional linked plan snapshot.');
            $table->string('feature_key')->index()->comment('Feature key this usage row belongs to.');
            $table->string('period', 32)->comment('Usage period type, e.g. daily, monthly, subscription_cycle.');
            $table->timestamp('period_start')->comment('Usage window start timestamp.');
            $table->timestamp('period_end')->comment('Usage window end timestamp.');
            $table->unsignedBigInteger('used')->default(0)->comment('Consumed amount within the period window.');
            $table->unsignedBigInteger('limit_value')->default(0)->comment('Configured limit value for the same window.');
            $table->timestamp('reset_at')->nullable()->index()->comment('Next scheduled reset timestamp for this usage row.');
            $table->json('metadata')->nullable()->comment('Safe extension metadata for diagnostics and tuning.');
            $table->timestamps();

            $table->unique(
                ['user_id', 'feature_key', 'period', 'period_start', 'period_end'],
                'feature_usages_user_feature_period_window_uniq'
            );
            $table->index(['subscription_id'], 'feature_usages_subscription_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE feature_usages COMMENT = 'Feature usage ledger for enforcing plan limits across modules.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_usages');
    }
};

