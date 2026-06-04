<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payer_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete()->comment('Explicit payer user; legacy user_id remains supported.');
            $table->foreignId('company_id')->nullable()->after('payer_user_id')->constrained('companies')->nullOnDelete()->comment('Optional company reporting and access scope.');
            $table->foreignId('seller_id')->nullable()->after('company_id')->constrained('sellers')->nullOnDelete()->comment('Optional seller or merchant reporting and access scope.');
            $table->foreignId('provider_account_id')->nullable()->after('seller_id')->constrained('payment_provider_accounts')->nullOnDelete()->comment('Provider account selected for this payment attempt.');
            $table->json('ownership_metadata')->nullable()->after('metadata')->comment('Safe ownership resolution snapshot for reporting and webhook routing readiness.');

            $table->index('payer_user_id', 'payments_payer_user_idx');
            $table->index('company_id', 'payments_company_idx');
            $table->index('seller_id', 'payments_seller_idx');
            $table->index('provider_account_id', 'payments_provider_account_idx');
            $table->index(['company_id', 'seller_id'], 'payments_company_seller_idx');
            $table->index(['seller_id', 'payer_user_id'], 'payments_seller_payer_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_company_seller_idx');
            $table->dropIndex('payments_seller_payer_idx');
            $table->dropIndex('payments_payer_user_idx');
            $table->dropIndex('payments_company_idx');
            $table->dropIndex('payments_seller_idx');
            $table->dropIndex('payments_provider_account_idx');
            $table->dropConstrainedForeignId('provider_account_id');
            $table->dropConstrainedForeignId('seller_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('payer_user_id');
            $table->dropColumn('ownership_metadata');
        });
    }
};
