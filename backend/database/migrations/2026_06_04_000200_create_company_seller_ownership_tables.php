<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id()->comment('Primary company ownership scope ID.');
            $table->uuid('uuid')->unique()->comment('Public stable company identifier.');
            $table->string('name')->comment('Company display name.');
            $table->string('slug')->nullable()->unique()->comment('Optional stable machine-readable company key.');
            $table->string('status', 32)->default('active')->index()->comment('Company lifecycle status: active, inactive, or suspended.');
            $table->json('metadata')->nullable()->comment('Safe company extension metadata; must not contain secrets.');
            $table->timestamps();
        });

        Schema::create('sellers', function (Blueprint $table) {
            $table->id()->comment('Primary seller or merchant ownership scope ID.');
            $table->uuid('uuid')->unique()->comment('Public stable seller identifier.');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete()->comment('Optional parent company ownership scope.');
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete()->comment('User responsible for this seller scope.');
            $table->string('name')->comment('Seller or merchant display name.');
            $table->string('slug')->nullable()->unique()->comment('Optional stable machine-readable seller key.');
            $table->string('status', 32)->default('active')->index()->comment('Seller lifecycle status: active, inactive, or suspended.');
            $table->json('metadata')->nullable()->comment('Safe seller extension metadata; must not contain secrets.');
            $table->timestamps();

            $table->index('company_id', 'sellers_company_idx');
            $table->index('owner_user_id', 'sellers_owner_user_idx');
        });

        Schema::create('company_users', function (Blueprint $table) {
            $table->id()->comment('Primary company membership ID.');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete()->comment('Company this membership grants access to.');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('User participating in the company.');
            $table->string('role', 32)->default('viewer')->comment('Company-local role: owner, admin, manager, or viewer.');
            $table->string('status', 32)->default('active')->index()->comment('Membership status: active, invited, or suspended.');
            $table->json('metadata')->nullable()->comment('Safe company membership extension metadata.');
            $table->timestamps();

            $table->unique(['company_id', 'user_id'], 'company_users_company_user_uniq');
            $table->index(['user_id', 'status'], 'company_users_user_status_idx');
        });

        Schema::create('seller_customers', function (Blueprint $table) {
            $table->id()->comment('Primary seller-to-customer relationship ID.');
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete()->comment('Seller serving this customer.');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('Existing platform user acting as seller customer.');
            $table->string('status', 32)->default('active')->index()->comment('Seller customer status: active, blocked, or inactive.');
            $table->json('metadata')->nullable()->comment('Safe seller customer relationship metadata.');
            $table->timestamps();

            $table->unique(['seller_id', 'user_id'], 'seller_customers_seller_user_uniq');
            $table->index(['user_id', 'status'], 'seller_customers_user_status_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE companies COMMENT = 'Top-level billing ownership scope for sellers, payments, providers, and future reports.'");
            DB::statement("ALTER TABLE sellers COMMENT = 'Seller or merchant ownership scopes optionally grouped under a company.'");
            DB::statement("ALTER TABLE company_users COMMENT = 'Company membership and company-local access role assignments.'");
            DB::statement("ALTER TABLE seller_customers COMMENT = 'Links existing platform users to sellers as customers or end users.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_customers');
        Schema::dropIfExists('company_users');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('companies');
    }
};
