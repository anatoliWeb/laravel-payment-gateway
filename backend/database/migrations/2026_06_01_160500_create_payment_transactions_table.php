<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id()->comment('Primary payment transaction timeline ID.');
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete()->comment('Payment attempt this timeline record belongs to.');
            $table->string('type', 64)->index()->comment('Transaction event type, e.g. payment_succeeded.');
            $table->string('status_from', 32)->nullable()->comment('Previous payment status before transition.');
            $table->string('status_to', 32)->nullable()->index()->comment('New payment status after transition.');
            $table->unsignedBigInteger('amount')->nullable()->comment('Optional amount snapshot in minor currency units.');
            $table->string('currency', 3)->nullable()->comment('Optional currency snapshot for this event.');
            $table->string('message')->nullable()->comment('Optional short operator-readable event message.');
            $table->json('payload')->nullable()->comment('Safe event payload snapshot without secrets.');
            $table->timestamps();

            $table->index(['payment_id', 'created_at'], 'payment_transactions_payment_created_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payment_transactions COMMENT = 'Append-only timeline of payment state transitions and side effects.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};

