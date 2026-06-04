<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_provider_accounts', function (Blueprint $table) {
            $table->id()->comment('Primary payment provider account ID.');
            $table->uuid('uuid')->unique()->comment('Public unique identifier for the provider account.');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('Current customer/user owner of this provider account.');
            $table->string('provider', 50)->index()->comment('Provider adapter key such as simulator, stripe, paypal, liqpay, or wayforpay.');
            $table->string('display_name')->nullable()->comment('Safe user-facing provider account label.');
            $table->string('status', 32)->default('inactive')->index()->comment('Provider account status: active, inactive, invalid, or disabled.');
            $table->string('mode', 16)->default('test')->index()->comment('Provider operation mode: test or live.');
            $table->string('config_source', 20)->default('database')->comment('Credential source: database or env.');
            $table->longText('encrypted_credentials')->nullable()->comment('Laravel-encrypted provider credentials payload; never expose or log decrypted values.');
            $table->json('public_config')->nullable()->comment('Non-secret provider configuration safe for internal inspection.');
            $table->json('capabilities')->nullable()->comment('Optional cached provider capability map.');
            $table->timestamp('last_verified_at')->nullable()->comment('Timestamp when provider credentials were last verified.');
            $table->json('metadata')->nullable()->comment('Safe metadata only; must not contain credentials or provider secrets.');
            $table->timestamps();

            $table->index(['user_id', 'provider', 'status'], 'provider_accounts_user_provider_status_idx');
            $table->index(['provider', 'mode'], 'provider_accounts_provider_mode_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payment_provider_accounts COMMENT = 'Customer-owned payment provider configuration with encrypted credentials.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_accounts');
    }
};
