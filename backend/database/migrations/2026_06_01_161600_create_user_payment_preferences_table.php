<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_payment_preferences', function (Blueprint $table) {
            $table->id()->comment('Primary user payment preference ID.');
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete()->comment('Owner user; one payment preference row is allowed per user.');
            $table->foreignId('default_payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete()->comment('Preferred simulator payment method for future payment flows.');
            $table->string('strategy', 40)->default('wallet_first')->index()->comment('Payment strategy: wallet_only, payment_method_only, wallet_first, or manual_invoice.');
            $table->boolean('auto_charge_enabled')->default(false)->index()->comment('Whether future automatic charges are allowed after explicit consent.');
            $table->boolean('auto_top_up_enabled')->default(false)->index()->comment('Whether future automatic wallet top-up is allowed after explicit consent.');
            $table->unsignedBigInteger('auto_top_up_threshold_amount')->nullable()->comment('Minor-unit wallet threshold for future auto top-up.');
            $table->unsignedBigInteger('auto_top_up_amount')->nullable()->comment('Minor-unit amount to add during future auto top-up.');
            $table->foreignId('auto_top_up_currency_id')->nullable()->constrained('currencies')->nullOnDelete()->comment('Currency used by future auto top-up preferences.');
            $table->unsignedInteger('max_auto_top_up_per_day')->nullable()->comment('Future daily auto top-up safety limit.');
            $table->unsignedInteger('max_auto_top_up_per_month')->nullable()->comment('Future monthly auto top-up safety limit.');
            $table->timestamp('auto_charge_consent_at')->nullable()->comment('Timestamp of explicit consent for future automatic charges.');
            $table->timestamp('auto_top_up_consent_at')->nullable()->comment('Timestamp of explicit consent for future automatic wallet top-up.');
            $table->json('metadata')->nullable()->comment('Safe extension payload for preference context; must not contain secrets.');
            $table->timestamps();

            $table->index('user_id', 'user_payment_preferences_user_id_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE user_payment_preferences COMMENT = 'User billing strategy and explicit consent preferences for simulated payment flows.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payment_preferences');
    }
};
