<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_restrictions', function (Blueprint $table) {
            $table->id()->comment('Primary billing restriction ID.');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('User affected by this billing restriction.');
            $table->string('type', 64)->index()->comment('Restriction type: billing_blocked, payment_blocked, or feature_blocked.');
            $table->string('scope', 32)->index()->comment('Restriction scope: billing, payment, or feature.');
            $table->string('feature_key')->nullable()->index()->comment('Optional module-agnostic feature key affected by this restriction.');
            $table->string('reason')->nullable()->comment('Safe operator-readable or machine-readable restriction reason.');
            $table->boolean('is_active')->default(true)->index()->comment('Whether this restriction is enabled.');
            $table->timestamp('starts_at')->nullable()->index()->comment('Optional activation timestamp for temporary restrictions.');
            $table->timestamp('ends_at')->nullable()->index()->comment('Optional expiration timestamp for temporary restrictions.');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('Optional admin/operator user who created this restriction.');
            $table->json('metadata')->nullable()->comment('Safe extension metadata for audit and diagnostics.');
            $table->timestamps();

            $table->index(['user_id'], 'billing_restrictions_user_idx');
            $table->index(['user_id', 'type', 'is_active'], 'billing_restrictions_user_type_active_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE billing_restrictions COMMENT = 'Manual billing, payment, and feature restrictions applied to users.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_restrictions');
    }
};
