<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique('idempotency_keys_key_method_endpoint_uniq');

            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Actor that owns this idempotency key namespace.');
            $table->string('scope', 128)
                ->nullable()
                ->after('key')
                ->comment('Stable operation scope such as payment.create or wallet.top_up.');

            $table->unique(
                ['user_id', 'key', 'scope'],
                'idempotency_keys_user_key_scope_uniq',
            );
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique('idempotency_keys_user_key_scope_uniq');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('scope');
            $table->unique(
                ['key', 'method', 'endpoint'],
                'idempotency_keys_key_method_endpoint_uniq',
            );
        });
    }
};
