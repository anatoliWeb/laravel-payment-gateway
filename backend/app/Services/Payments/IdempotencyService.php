<?php

namespace App\Services\Payments;

use App\Models\IdempotencyKey;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class IdempotencyService
{
    private const LOCK_MINUTES = 5;

    private const TTL_HOURS = 24;

    private const FORBIDDEN_KEYS = [
        'card_number',
        'number',
        'pan',
        'cvv',
        'cvc',
        'security_code',
        'token',
        'secret',
        'password',
        'private_key',
        'provider_config',
        'credentials',
    ];

    public function __construct(
        private readonly ActivityService $activityService,
    ) {}

    public function start(string $key, string $scope, array $payload, User $user): IdempotencyKey
    {
        $this->assertKeyPresent($key);
        $requestHash = $this->payloadHash($payload);
        $keyHash = $this->keyHash($key);

        try {
            return DB::transaction(function () use ($keyHash, $scope, $user, $requestHash): IdempotencyKey {
                $record = $this->query($keyHash, $scope, $user)
                    ->lockForUpdate()
                    ->first();

                if ($record) {
                    return $this->restartOrReturn($record, $requestHash);
                }

                return IdempotencyKey::query()->create([
                    'user_id' => $user->id,
                    'key' => $keyHash,
                    'scope' => $scope,
                    'method' => 'SERVICE',
                    'endpoint' => $scope,
                    'request_hash' => $requestHash,
                    'status' => 'processing',
                    'locked_until' => now()->addMinutes(self::LOCK_MINUTES),
                    'expires_at' => now()->addHours(self::TTL_HOURS),
                ]);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $record = $this->query($keyHash, $scope, $user)->first();
            if (! $record) {
                throw $exception;
            }

            $this->assertHashMatches($record, $requestHash);
            if ($record->status === 'processing') {
                throw new RuntimeException('idempotency_request_processing');
            }

            return $record;
        }
    }

    public function complete(
        IdempotencyKey $record,
        mixed $responsePayload,
        ?int $resourceId = null,
        ?string $resourceType = null,
    ): void {
        $record->update([
            'status' => 'completed',
            'response_body' => $this->normalizeResponsePayload($responsePayload),
            'response_status' => 201,
            'related_id' => $resourceId,
            'related_type' => $resourceType,
            'locked_until' => null,
        ]);
    }

    public function fail(IdempotencyKey $record, string $errorCode, array $errorPayload = []): void
    {
        $record->update([
            'status' => 'failed',
            'response_body' => [
                'error_code' => $this->normalizeErrorCode($errorCode),
                'error_payload' => $this->sanitize($errorPayload),
            ],
            'response_status' => 422,
            'locked_until' => null,
        ]);
    }

    public function replay(string $key, string $scope, array $payload, User $user): ?array
    {
        $this->assertKeyPresent($key);

        $record = $this->query($this->keyHash($key), $scope, $user)->first();
        if (! $record) {
            return null;
        }

        if ($record->expires_at?->isPast()) {
            return null;
        }

        $this->assertHashMatches($record, $this->payloadHash($payload));

        if ($record->status === 'processing' && $record->locked_until?->isFuture()) {
            throw new RuntimeException('idempotency_request_processing');
        }

        if (in_array($record->status, ['completed', 'failed'], true)) {
            $this->recordActivity($record, 'billing.idempotency_replayed', 'Billing idempotency request replayed');

            return (array) $record->response_body;
        }

        return null;
    }

    public function assertNotConflicting(string $key, string $scope, array $payload, User $user): void
    {
        $this->assertKeyPresent($key);

        $record = $this->query($this->keyHash($key), $scope, $user)->first();
        if ($record && ! $record->expires_at?->isPast()) {
            $this->assertHashMatches($record, $this->payloadHash($payload));
        }
    }

    public function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->sortRecursively($this->sanitize($payload)),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }

    private function query(string $keyHash, string $scope, User $user)
    {
        return IdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $keyHash)
            ->where('scope', $scope);
    }

    private function assertKeyPresent(string $key): void
    {
        if (trim($key) === '') {
            throw new RuntimeException('idempotency_key_required');
        }
    }

    private function assertHashMatches(IdempotencyKey $record, string $requestHash): void
    {
        if (! hash_equals($record->request_hash, $requestHash)) {
            $this->recordActivity($record, 'billing.idempotency_conflict', 'Billing idempotency key conflict');

            throw new RuntimeException('idempotency_key_conflict');
        }
    }

    private function restartOrReturn(IdempotencyKey $record, string $requestHash): IdempotencyKey
    {
        if ($record->expires_at?->isPast()) {
            $record->status = 'expired';
        } else {
            $this->assertHashMatches($record, $requestHash);
        }

        if ($record->status === 'processing' && $record->locked_until?->isFuture()) {
            throw new RuntimeException('idempotency_request_processing');
        }

        if (in_array($record->status, ['completed', 'failed'], true)) {
            return $record;
        }

        $record->update([
            'status' => 'processing',
            'response_body' => null,
            'response_status' => null,
            'related_type' => null,
            'related_id' => null,
            'request_hash' => $requestHash,
            'locked_until' => now()->addMinutes(self::LOCK_MINUTES),
            'expires_at' => now()->addHours(self::TTL_HOURS),
        ]);

        return $record->refresh();
    }

    private function keyHash(string $key): string
    {
        return hash('sha256', trim($key));
    }

    private function recordActivity(IdempotencyKey $record, string $action, string $description): void
    {
        try {
            // WHY: Raw idempotency keys can be client secrets. Audit metadata
            // stores only the persisted hash and safe resource references.
            $this->activityService->log($record->user_id, $action, $description, [
                'source' => 'idempotency_service',
                'module' => 'billing',
                'idempotency_key_id' => $record->id,
                'key_hash' => $record->key,
                'scope' => $record->scope,
                'status' => $record->status,
                'resource_type' => $record->related_type,
                'resource_id' => $record->related_id,
                'response_status' => $record->response_status,
            ]);
        } catch (Throwable) {
            // Activity logging must not change idempotency behavior.
        }
    }

    private function normalizeErrorCode(string $errorCode): string
    {
        $errorCode = trim($errorCode);

        return preg_match('/^[a-z][a-z0-9_.-]{2,127}$/', $errorCode) === 1
            ? $errorCode
            : 'idempotency_operation_failed';
    }

    private function normalizeResponsePayload(mixed $payload): array
    {
        return $this->sanitize(is_array($payload) ? $payload : ['result' => $payload]);
    }

    private function sanitize(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN_KEYS, true)) {
                unset($payload[$key]);

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->sanitize($value);
            }
        }

        return $payload;
    }

    private function sortRecursively(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortRecursively($value);
            }
        }

        if (! array_is_list($payload)) {
            ksort($payload);
        }

        return $payload;
    }
}
