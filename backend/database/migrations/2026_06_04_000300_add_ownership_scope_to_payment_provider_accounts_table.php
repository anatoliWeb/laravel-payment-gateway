<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_provider_accounts', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained('companies')->nullOnDelete()->comment('Optional company provider configuration scope.');
            $table->foreignId('seller_id')->nullable()->after('company_id')->constrained('sellers')->nullOnDelete()->comment('Optional seller-specific provider configuration scope.');

            $table->index(['seller_id', 'provider', 'status'], 'provider_accounts_seller_provider_status_idx');
            $table->index(['company_id', 'provider', 'status'], 'provider_accounts_company_provider_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payment_provider_accounts', function (Blueprint $table) {
            $table->dropIndex('provider_accounts_seller_provider_status_idx');
            $table->dropIndex('provider_accounts_company_provider_status_idx');
            $table->dropConstrainedForeignId('seller_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
