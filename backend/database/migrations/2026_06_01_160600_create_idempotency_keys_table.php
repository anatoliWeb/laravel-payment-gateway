<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id()->comment('Primary idempotency record ID.');
            $table->string('key', 128)->comment('Client-provided idempotency key.');
            $table->string('method', 16)->comment('HTTP method bound to this idempotency key.');
            $table->string('endpoint', 255)->comment('Normalized endpoint path bound to this idempotency key.');
            $table->string('request_hash', 128)->comment('Deterministic request payload hash for replay/conflict checks.');
            $table->json('response_body')->nullable()->comment('Safe cached response payload for idempotent replay.');
            $table->unsignedSmallInteger('response_status')->nullable()->comment('HTTP response status cached for replay.');
            $table->string('related_type', 64)->nullable()->comment('Optional related entity type for diagnostics.');
            $table->unsignedBigInteger('related_id')->nullable()->comment('Optional related entity ID for diagnostics.');
            $table->string('status', 32)->index()->comment('Current idempotency lifecycle state.');
            $table->timestamp('locked_until')->nullable()->comment('Concurrency lock expiry timestamp.');
            $table->timestamp('expires_at')->nullable()->index()->comment('TTL expiration timestamp for cleanup tasks.');
            $table->timestamps();

            $table->unique(['key', 'method', 'endpoint'], 'idempotency_keys_key_method_endpoint_uniq');
            $table->index(['related_type', 'related_id'], 'idempotency_keys_related_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE idempotency_keys COMMENT = 'Idempotency request registry for safe payment create/retry operations.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};

