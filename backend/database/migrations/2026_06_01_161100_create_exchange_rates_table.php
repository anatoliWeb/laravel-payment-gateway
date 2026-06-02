<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id()->comment('Primary exchange rate ID.');
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete()->comment('Currency being converted from.');
            $table->foreignId('quote_currency_id')->constrained('currencies')->restrictOnDelete()->comment('Currency being converted to.');
            $table->decimal('rate', 20, 8)->comment('Conversion rate from base currency to quote currency stored with decimal precision.');
            $table->string('source', 64)->default('manual')->comment('Rate source identifier such as manual or simulated.');
            $table->timestamp('valid_from')->index()->comment('Start timestamp for rate validity.');
            $table->timestamp('valid_until')->nullable()->index()->comment('Optional end timestamp for historical rate validity.');
            $table->boolean('is_active')->default(true)->index()->comment('Whether this rate is eligible for active lookup.');
            $table->json('metadata')->nullable()->comment('Safe extension payload for audit and diagnostics.');
            $table->timestamps();

            $table->index(['base_currency_id'], 'exchange_rates_base_currency_idx');
            $table->index(['quote_currency_id'], 'exchange_rates_quote_currency_idx');
            $table->index(['base_currency_id', 'quote_currency_id'], 'exchange_rates_pair_idx');
            $table->index(['base_currency_id', 'quote_currency_id', 'is_active', 'valid_from'], 'exchange_rates_active_lookup_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE exchange_rates COMMENT = 'Manual or simulated exchange rates for multi-currency billing and wallets.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
