<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_overrides', function (Blueprint $table) {
            $table->id()->comment('Primary feature override ID.');
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete()->comment('Optional user-level override owner.');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->cascadeOnDelete()->comment('Optional subscription-level override owner.');
            $table->string('feature_key')->index()->comment('Module-agnostic feature key affected by this override.');
            $table->string('value')->comment('Override value stored as string and interpreted by value_type.');
            $table->string('value_type', 32)->comment('Override value type, e.g. boolean, integer, decimal, string, json.');
            $table->string('period', 32)->default('none')->index()->comment('Usage/access period this override applies to.');
            $table->string('reset_policy', 32)->nullable()->comment('Optional reset policy aligned with plan feature semantics.');
            $table->boolean('is_enabled')->default(true)->index()->comment('Whether this override allows/enables the feature or denies it.');
            $table->integer('priority')->default(100)->index()->comment('Higher priority overrides lower priority within the same owner level.');
            $table->string('reason')->nullable()->comment('Safe admin/operator note explaining why this override exists.');
            $table->timestamp('starts_at')->nullable()->index()->comment('Optional activation timestamp for temporary overrides.');
            $table->timestamp('ends_at')->nullable()->index()->comment('Optional expiration timestamp for temporary overrides.');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('Optional admin/operator user who created this override.');
            $table->json('metadata')->nullable()->comment('Safe extension metadata for audit and diagnostics.');
            $table->timestamps();

            $table->index(['user_id'], 'feature_overrides_user_idx');
            $table->index(['subscription_id'], 'feature_overrides_subscription_idx');
            $table->index(['user_id', 'feature_key', 'period'], 'feature_overrides_user_feature_period_idx');
            $table->index(['subscription_id', 'feature_key', 'period'], 'feature_overrides_subscription_feature_period_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE feature_overrides COMMENT = 'Manual user and subscription feature overrides for billing access and limits.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_overrides');
    }
};
