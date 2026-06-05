<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id()->comment('Primary invoice ID.');
            $table->uuid('uuid')->unique()->comment('Public unique invoice identifier.');
            $table->string('number', 64)->nullable()->unique()->comment('Human-readable invoice number assigned when invoice is issued.');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Legacy owner user for backward-compatible user-scoped billing.');
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('User expected to pay this invoice.');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete()->comment('Optional company ownership/reporting scope.');
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete()->comment('Optional seller or merchant ownership/reporting scope.');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete()->comment('Optional subscription context; activation remains outside invoice flow.');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete()->comment('Latest payment attempt linked to this invoice.');
            $table->string('status', 32)->index()->comment('Invoice lifecycle status stored as stable string value.');
            $table->string('currency', 3)->comment('ISO-like 3-letter currency; amounts are stored in minor units.');
            $table->unsignedBigInteger('subtotal_amount')->default(0)->comment('Sum of item subtotals in minor units before discounts and taxes.');
            $table->unsignedBigInteger('discount_amount')->default(0)->comment('Total discount amount in minor units.');
            $table->unsignedBigInteger('tax_amount')->default(0)->comment('Total tax amount in minor units; no tax engine is implemented here.');
            $table->unsignedBigInteger('total_amount')->default(0)->comment('Final invoice total in minor units.');
            $table->unsignedBigInteger('paid_amount')->default(0)->comment('Amount paid against this invoice in minor units.');
            $table->unsignedBigInteger('due_amount')->default(0)->comment('Remaining amount due in minor units.');
            $table->timestamp('issued_at')->nullable()->comment('Timestamp when draft invoice became issued.');
            $table->timestamp('due_at')->nullable()->index()->comment('Optional payment due timestamp.');
            $table->timestamp('paid_at')->nullable()->comment('Timestamp when invoice became fully paid.');
            $table->timestamp('voided_at')->nullable()->comment('Timestamp when invoice was voided before payment completion.');
            $table->timestamp('overdue_at')->nullable()->comment('Timestamp when invoice was marked overdue.');
            $table->string('description')->nullable()->comment('Safe human-readable invoice summary.');
            $table->json('metadata')->nullable()->comment('Safe extension payload; must not contain provider secrets or raw payment data.');
            $table->json('ownership_metadata')->nullable()->comment('Snapshot of ownership resolution for reporting and audit.');
            $table->timestamps();

            $table->index('number', 'invoices_number_idx');
            $table->index('user_id', 'invoices_user_idx');
            $table->index('payer_user_id', 'invoices_payer_user_idx');
            $table->index('company_id', 'invoices_company_idx');
            $table->index('seller_id', 'invoices_seller_idx');
            $table->index('subscription_id', 'invoices_subscription_idx');
            $table->index('payment_id', 'invoices_payment_idx');
            $table->index('issued_at', 'invoices_issued_at_idx');
            $table->index(['company_id', 'seller_id'], 'invoices_company_seller_idx');
            $table->index(['seller_id', 'payer_user_id'], 'invoices_seller_payer_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoices COMMENT = 'Invoices provide financial context for payment attempts without activating subscriptions.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
