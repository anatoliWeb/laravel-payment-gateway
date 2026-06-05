<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id()->comment('Primary invoice item ID.');
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete()->comment('Parent invoice for this line item.');
            $table->string('item_type', 64)->nullable()->index()->comment('Optional stable line item type, such as subscription_plan or usage_charge.');
            $table->string('description')->comment('Human-readable item description.');
            $table->unsignedInteger('quantity')->default(1)->comment('Positive item quantity; service validation prevents zero quantity.');
            $table->unsignedBigInteger('unit_amount')->comment('Unit amount in minor currency units.');
            $table->unsignedBigInteger('subtotal_amount')->comment('Quantity multiplied by unit amount in minor units.');
            $table->unsignedBigInteger('discount_amount')->default(0)->comment('Discount amount for this line in minor units.');
            $table->unsignedBigInteger('tax_amount')->default(0)->comment('Tax amount for this line in minor units; no tax engine is implemented.');
            $table->unsignedBigInteger('total_amount')->comment('Final line total in minor units.');
            $table->json('metadata')->nullable()->comment('Safe extension payload for invoice item context.');
            $table->timestamps();

            $table->index('invoice_id', 'invoice_items_invoice_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoice_items COMMENT = 'Invoice line items with integer minor-unit amounts only.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
