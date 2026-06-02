<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id()->comment('Primary plan ID.');
            $table->uuid('uuid')->unique()->comment('Public unique plan identifier.');
            $table->string('slug')->unique()->comment('Stable machine-readable plan key used by seeders, API contracts, and feature checks.');
            $table->string('name')->comment('Human-readable plan name shown to users.');
            $table->text('description')->nullable()->comment('Optional short description of plan scope.');
            $table->string('type', 32)->index()->comment('Plan type stored as string and later cast to PHP enum.');
            $table->unsignedBigInteger('price_amount')->comment('Plan price stored in minor currency units.');
            $table->string('currency', 3)->comment('ISO-like 3-letter billing currency code.');
            $table->string('billing_interval', 32)->comment('Billing interval stored as string for future enum casting.');
            $table->unsignedInteger('trial_days')->default(0)->comment('Trial length in days for this plan.');
            $table->boolean('is_active')->default(true)->index()->comment('Whether this plan can currently be used.');
            $table->boolean('is_public')->default(true)->index()->comment('Whether this plan is visible in public plan listings.');
            $table->unsignedInteger('sort_order')->default(0)->index()->comment('Display ordering priority in plan lists.');
            $table->json('metadata')->nullable()->comment('Safe extension payload; must not contain secrets or raw payment data.');
            $table->timestamps();

            $table->index(['is_active', 'is_public'], 'plans_active_public_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE plans COMMENT = 'Billing catalog plans used for subscriptions and feature access decisions.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
