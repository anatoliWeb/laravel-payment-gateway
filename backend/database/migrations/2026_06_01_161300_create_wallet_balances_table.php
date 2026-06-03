<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_balances', function (Blueprint $table) {
            $table->id()->comment('Primary wallet balance ID.');
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete()->comment('Wallet that owns this currency balance.');
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete()->comment('Currency for this balance row.');
            $table->unsignedBigInteger('available_amount')->default(0)->comment('Spendable amount stored in minor currency units.');
            $table->unsignedBigInteger('held_amount')->default(0)->comment('Reserved amount stored in minor currency units.');
            $table->json('metadata')->nullable()->comment('Safe extension payload for diagnostics and future policies.');
            $table->timestamps();

            $table->unique(['wallet_id', 'currency_id'], 'wallet_balances_wallet_currency_unique');
            $table->index(['currency_id'], 'wallet_balances_currency_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE wallet_balances COMMENT = 'Per-currency wallet balances with available and held amounts.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_balances');
    }
};
