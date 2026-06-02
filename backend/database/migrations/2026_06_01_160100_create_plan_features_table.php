<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id()->comment('Primary plan feature ID.');
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete()->comment('Plan that owns this feature policy.');
            $table->string('feature_key')->index()->comment('Stable feature key, e.g. chat.messages.monthly.');
            $table->string('value')->comment('Feature value stored as string and interpreted by value_type.');
            $table->string('value_type', 32)->comment('Value type, e.g. integer, boolean, decimal, string.');
            $table->string('period', 32)->default('none')->comment('Usage period key; non-null to keep uniqueness deterministic.');
            $table->string('reset_policy', 32)->default('none')->comment('Counter reset policy for usage-based features.');
            $table->boolean('is_enabled')->default(true)->comment('Whether this feature policy is enabled for the plan.');
            $table->json('metadata')->nullable()->comment('Safe extension metadata for future tuning.');
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key', 'period'], 'plan_features_plan_feature_period_uniq');
            $table->index(['plan_id', 'feature_key'], 'plan_features_plan_feature_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE plan_features COMMENT = 'Per-plan feature configuration and usage limits for billing modules.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
