<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id()->comment('Primary wallet transaction ID.');
            $table->uuid('uuid')->unique()->comment('Public unique wallet transaction identifier.');
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete()->comment('Wallet affected by this ledger entry.');
            $table->foreignId('wallet_balance_id')->nullable()->constrained('wallet_balances')->nullOnDelete()->comment('Specific balance row affected by this ledger entry.');
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete()->comment('Currency of this ledger amount.');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete()->comment('Optional payment linked to this wallet movement.');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete()->comment('Optional subscription context for this wallet movement.');
            $table->string('type', 32)->index()->comment('Wallet transaction type: top_up, debit, hold, release, refund, or adjustment.');
            $table->string('direction', 16)->index()->comment('Ledger direction: credit, debit, or neutral.');
            $table->unsignedBigInteger('amount')->comment('Transaction amount stored in minor currency units.');
            $table->unsignedBigInteger('balance_available_before')->nullable()->comment('Available balance before this transaction.');
            $table->unsignedBigInteger('balance_available_after')->nullable()->comment('Available balance after this transaction.');
            $table->unsignedBigInteger('balance_held_before')->nullable()->comment('Held balance before this transaction.');
            $table->unsignedBigInteger('balance_held_after')->nullable()->comment('Held balance after this transaction.');
            $table->string('idempotency_key')->nullable()->index()->comment('Optional local idempotency key for wallet balance operations.');
            $table->string('reference_type')->nullable()->comment('Optional related domain model type.');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('Optional related domain model ID.');
            $table->string('reason')->nullable()->comment('Safe machine-readable reason or operator note.');
            $table->string('status', 32)->default('completed')->index()->comment('Wallet transaction status: pending, completed, failed, or cancelled.');
            $table->json('metadata')->nullable()->comment('Safe extension payload; must not contain raw payment data.');
            $table->timestamps();

            $table->index(['wallet_id', 'currency_id'], 'wallet_transactions_wallet_currency_idx');
            $table->index(['reference_type', 'reference_id'], 'wallet_transactions_reference_idx');
            $table->index(['wallet_id', 'idempotency_key', 'status'], 'wallet_transactions_idempotency_lookup_idx');
            $table->unique(['wallet_id', 'idempotency_key'], 'wallet_transactions_wallet_idempotency_unique');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE wallet_transactions COMMENT = 'Append-only wallet ledger entries for internal balance operations.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
