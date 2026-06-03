<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id()->comment('Primary wallet ID.');
            $table->uuid('uuid')->unique()->comment('Public unique wallet identifier.');
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete()->comment('Owner user; one wallet is allowed per user.');
            $table->string('status', 32)->default('active')->index()->comment('Wallet lifecycle state: active, suspended, or closed.');
            $table->json('metadata')->nullable()->comment('Safe extension payload; must not contain secrets.');
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE wallets COMMENT = 'User internal wallets used for future balance-based billing payments.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
