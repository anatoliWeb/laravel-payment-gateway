<?php

namespace App\Services\Chat;

use App\Models\Plan;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\Billing\FeatureAccessService;
use App\Services\Billing\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatBillingGuard
{
    public const MESSAGES_DAILY = 'chat.messages.daily';
    public const MESSAGES_MONTHLY = 'chat.messages.monthly';
    public const WEBHOOK_ENDPOINTS_COUNT = 'chat.webhook_endpoints.count';
    public const ATTACHMENTS_MONTHLY = 'chat.attachments.monthly';

    public function __construct(
        private readonly FeatureAccessService $featureAccessService,
        private readonly UsageLimitService $usageLimitService,
        private readonly ActivityService $activityService,
    ) {
    }

    /**
     * @return array{allowed: bool, feature_key: string, reason: string|null, usage?: array<string, mixed>}
     */
    public function checkMessageCreation(User $user): array
    {
        if (! $this->isBillingCatalogConfigured()) {
            return $this->allow(self::MESSAGES_DAILY);
        }

        foreach ([self::MESSAGES_DAILY, self::MESSAGES_MONTHLY] as $featureKey) {
            $usage = $this->usageLimitService->checkUsageLimit($user, $featureKey, 1);

            if (! (bool) $usage['allowed']) {
                return $this->deny($featureKey, $usage['reason'], ['usage' => $usage]);
            }
        }

        return $this->allow(self::MESSAGES_DAILY);
    }

    public function recordMessageCreated(User $user): void
    {
        if (! $this->isBillingCatalogConfigured()) {
            return;
        }

        $this->usageLimitService->incrementUsage($user, self::MESSAGES_DAILY);
        $this->usageLimitService->incrementUsage($user, self::MESSAGES_MONTHLY);
    }

    /**
     * @return array{allowed: bool, feature_key: string, reason: string|null, usage?: array<string, mixed>}
     */
    public function checkAttachmentUpload(User $user): array
    {
        if (! $this->isBillingCatalogConfigured()) {
            return $this->allow(self::ATTACHMENTS_MONTHLY);
        }

        $usage = $this->usageLimitService->checkUsageLimit($user, self::ATTACHMENTS_MONTHLY, 1);

        if (! (bool) $usage['allowed']) {
            return $this->deny(self::ATTACHMENTS_MONTHLY, $usage['reason'], ['usage' => $usage]);
        }

        return $this->allow(self::ATTACHMENTS_MONTHLY);
    }

    public function recordAttachmentUploaded(User $user): void
    {
        if (! $this->isBillingCatalogConfigured()) {
            return;
        }

        $this->usageLimitService->incrementUsage($user, self::ATTACHMENTS_MONTHLY);
    }

    /**
     * @return array{allowed: bool, feature_key: string, reason: string|null, current: int, limit: int|null, remaining: int|null, plan_slug: string|null}
     */
    public function checkWebhookEndpointCreation(User $user, int $currentCount): array
    {
        if (! $this->isBillingCatalogConfigured()) {
            return [
                'allowed' => true,
                'feature_key' => self::WEBHOOK_ENDPOINTS_COUNT,
                'reason' => null,
                'current' => $currentCount,
                'limit' => null,
                'remaining' => null,
                'plan_slug' => null,
            ];
        }

        $availability = $this->featureAccessService->checkFeatureAvailability($user, self::WEBHOOK_ENDPOINTS_COUNT);
        $limit = is_numeric($availability['value']) ? (int) $availability['value'] : null;

        if (! (bool) $availability['allowed']) {
            return [
                'allowed' => false,
                'feature_key' => self::WEBHOOK_ENDPOINTS_COUNT,
                'reason' => $availability['reason'],
                'current' => $currentCount,
                'limit' => $limit,
                'remaining' => $limit !== null ? max(0, $limit - $currentCount) : null,
                'plan_slug' => $availability['plan_slug'],
            ];
        }

        if ($limit === null || $currentCount + 1 > $limit) {
            return [
                'allowed' => false,
                'feature_key' => self::WEBHOOK_ENDPOINTS_COUNT,
                'reason' => 'limit_exceeded',
                'current' => $currentCount,
                'limit' => $limit,
                'remaining' => $limit !== null ? max(0, $limit - $currentCount) : null,
                'plan_slug' => $availability['plan_slug'],
            ];
        }

        return [
            'allowed' => true,
            'feature_key' => self::WEBHOOK_ENDPOINTS_COUNT,
            'reason' => null,
            'current' => $currentCount,
            'limit' => $limit,
            'remaining' => max(0, $limit - $currentCount),
            'plan_slug' => $availability['plan_slug'],
        ];
    }

    /**
     * @param array<string, mixed> $check
     */
    public function limitExceededResponse(User $user, string $action, array $check): JsonResponse
    {
        $this->logDenied($user, $action, $check);

        return response()->json([
            'success' => false,
            'message' => 'Chat feature limit exceeded.',
            'code' => 'feature_limit_exceeded',
            'errors' => [
                'feature_key' => $check['feature_key'] ?? null,
                'reason' => $check['reason'] ?? null,
            ],
            'meta' => $this->buildResponseMeta($check),
        ], 403);
    }

    /**
     * @return array{allowed: bool, feature_key: string, reason: null}
     */
    private function allow(string $featureKey): array
    {
        return [
            'allowed' => true,
            'feature_key' => $featureKey,
            'reason' => null,
        ];
    }

    private function isBillingCatalogConfigured(): bool
    {
        // WHY: Existing chat tests and local demos can run before BillingSeeder.
        // Once any plan exists, chat billing becomes strict and missing feature
        // keys are treated as configuration errors by billing services.
        return Plan::query()->exists();
    }

    /**
     * @param array<string, mixed> $extra
     * @return array{allowed: bool, feature_key: string, reason: string|null}
     */
    private function deny(string $featureKey, ?string $reason, array $extra = []): array
    {
        return array_merge([
            'allowed' => false,
            'feature_key' => $featureKey,
            'reason' => $reason,
        ], $extra);
    }

    /**
     * @param array<string, mixed> $check
     * @return array<string, mixed>
     */
    private function buildResponseMeta(array $check): array
    {
        $usage = (array) ($check['usage'] ?? []);

        return [
            'feature_key' => $check['feature_key'] ?? null,
            'reason' => $check['reason'] ?? null,
            'used' => $usage['used'] ?? ($check['current'] ?? null),
            'limit' => $usage['limit'] ?? ($check['limit'] ?? null),
            'remaining' => $usage['remaining'] ?? ($check['remaining'] ?? null),
            'period' => $usage['period'] ?? null,
            'reset_at' => isset($usage['reset_at']) && $usage['reset_at'] !== null
                ? $usage['reset_at']->toISOString()
                : null,
            'plan_slug' => $check['plan_slug'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $check
     */
    private function logDenied(User $user, string $action, array $check): void
    {
        try {
            $this->activityService->log(
                userId: $user->id,
                action: 'chat.feature_limit_exceeded',
                description: 'Chat feature limit exceeded.',
                meta: [
                    'source' => 'chat',
                    'module' => 'chat',
                    'category' => 'billing',
                    'attempted_action' => $action,
                    'feature_key' => $check['feature_key'] ?? null,
                    'reason' => $check['reason'] ?? null,
                    'limit' => data_get($check, 'usage.limit', $check['limit'] ?? null),
                    'used' => data_get($check, 'usage.used', $check['current'] ?? null),
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('Chat billing limit activity log failed', [
                'user_id' => $user->id,
                'action' => $action,
                'feature_key' => $check['feature_key'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
