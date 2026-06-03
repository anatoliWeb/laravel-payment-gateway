<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id()->comment('Primary payment method ID.');
            $table->uuid('uuid')->unique()->comment('Public unique payment method identifier.');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('Owner user for this simulator-safe payment method.');
            $table->string('type', 40)->index()->comment('Payment method type: fake_card, fake_manual_invoice, or fake_wallet.');
            $table->string('provider', 40)->index()->comment('Simulator provider key: simulator, manual, or internal_wallet.');
            $table->string('status', 32)->default('active')->index()->comment('Payment method lifecycle status: active, inactive, expired, revoked, or failed.');
            $table->string('display_name')->nullable()->comment('Safe user-facing label such as Visa ending 4242.');
            $table->string('brand', 40)->nullable()->comment('Safe simulated card brand or method brand.');
            $table->string('last4', 4)->nullable()->comment('Last four simulated card digits only; raw card numbers are never stored.');
            $table->unsignedTinyInteger('exp_month')->nullable()->comment('Simulated card expiration month.');
            $table->unsignedSmallInteger('exp_year')->nullable()->comment('Simulated card expiration year.');
            $table->string('provider_reference')->nullable()->comment('Fake provider reference for simulator correlation only.');
            $table->boolean('is_default')->default(false)->index()->comment('Marks the preferred payment method for this user.');
            $table->timestamp('consent_given_at')->nullable()->comment('Timestamp when the user consented to save/use this simulated method.');
            $table->json('metadata')->nullable()->comment('Safe extension payload; must not contain secrets, raw card data, or CVV.');
            $table->timestamps();

            $table->index('user_id', 'payment_methods_user_id_idx');
            $table->index(['user_id', 'is_default'], 'payment_methods_user_default_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payment_methods COMMENT = 'Simulator-safe user payment instruments without raw card data.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
