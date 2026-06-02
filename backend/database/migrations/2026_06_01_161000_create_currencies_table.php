<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id()->comment('Primary currency ID.');
            $table->string('code', 3)->unique()->comment('Stable ISO-like currency code used in APIs, seeders, and billing records.');
            $table->string('name')->comment('Human-readable currency display name.');
            $table->string('symbol', 16)->nullable()->comment('Optional currency symbol used for display only.');
            $table->unsignedTinyInteger('decimal_precision')->default(2)->comment('Number of decimal digits used for display and minor-unit conversion.');
            $table->boolean('is_active')->default(true)->index()->comment('Whether this currency can be used by billing and wallet flows.');
            $table->boolean('is_base')->default(false)->index()->comment('Marks the system base currency for simulated exchange rates.');
            $table->text('description')->nullable()->comment('Optional operational description of this currency.');
            $table->json('metadata')->nullable()->comment('Safe extension payload; must not contain secrets.');
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE currencies COMMENT = 'Currency catalog used by billing, wallet balances, and multi-currency payments.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
