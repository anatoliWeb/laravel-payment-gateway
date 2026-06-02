<?php

namespace Tests\Feature\Billing;

use App\Models\ActivityLog;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\FeatureOverride;
use App\Models\FeatureUsage;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaidChatFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-15 10:30:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_message_creation_increments_daily_and_monthly_usage(): void
    {
        $user = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $plan = $this->createPlanWithChatLimits([
            'chat.messages.daily' => ['value' => 2, 'period' => 'daily'],
            'chat.messages.monthly' => ['value' => 10, 'period' => 'monthly'],
        ]);

        $conversation = $this->makeConversation($user);
        $this->addParticipant($conversation, $user);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Paid chat message',
        ])->assertCreated();

        $this->assertFeatureUsage($user, $plan, 'chat.messages.daily', 'daily', 1);
        $this->assertFeatureUsage($user, $plan, 'chat.messages.monthly', 'monthly', 1);
    }

    public function test_message_creation_returns_stable_limit_error_when_daily_limit_is_exceeded(): void
    {
        $user = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $plan = $this->createPlanWithChatLimits([
            'chat.messages.daily' => ['value' => 1, 'period' => 'daily'],
            'chat.messages.monthly' => ['value' => 10, 'period' => 'monthly'],
        ]);
        $this->createUsage($user, $plan, 'chat.messages.daily', 'daily', 1, 1);

        $conversation = $this->makeConversation($user);
        $this->addParticipant($conversation, $user);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Blocked by billing',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_limit_exceeded')
            ->assertJsonPath('errors.feature_key', 'chat.messages.daily')
            ->assertJsonPath('errors.reason', 'limit_exceeded')
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.used', 1);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Blocked by billing',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'chat.feature_limit_exceeded',
        ]);
    }

    public function test_feature_override_can_raise_message_limit_for_user(): void
    {
        $user = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $plan = $this->createPlanWithChatLimits([
            'chat.messages.daily' => ['value' => 1, 'period' => 'daily'],
            'chat.messages.monthly' => ['value' => 10, 'period' => 'monthly'],
        ]);
        $this->createUsage($user, $plan, 'chat.messages.daily', 'daily', 1, 1);

        FeatureOverride::factory()->numericLimit(2)->create([
            'user_id' => $user->id,
            'feature_key' => 'chat.messages.daily',
            'period' => 'daily',
        ]);

        $conversation = $this->makeConversation($user);
        $this->addParticipant($conversation, $user);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Override allowed',
        ])->assertCreated();

        $this->assertSame(2, (int) FeatureUsage::query()
            ->where('user_id', $user->id)
            ->where('feature_key', 'chat.messages.daily')
            ->where('period', 'daily')
            ->value('used'));
    }

    public function test_active_paid_subscription_uses_paid_chat_limits(): void
    {
        $user = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $free = $this->createPlanWithChatLimits([
            'chat.messages.daily' => ['value' => 1, 'period' => 'daily'],
            'chat.messages.monthly' => ['value' => 1, 'period' => 'monthly'],
        ]);
        $pro = $this->createPlanWithChatLimits([
            'chat.messages.daily' => ['value' => 5, 'period' => 'daily'],
            'chat.messages.monthly' => ['value' => 100, 'period' => 'monthly'],
        ], 'pro');

        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $pro->id,
            'status' => 'active',
        ]);
        $this->createUsage($user, $free, 'chat.messages.daily', 'daily', 1, 1);

        $conversation = $this->makeConversation($user);
        $this->addParticipant($conversation, $user);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Paid subscription allowed',
        ])->assertCreated();

        $this->assertSame(2, (int) FeatureUsage::query()
            ->where('user_id', $user->id)
            ->where('feature_key', 'chat.messages.daily')
            ->where('period', 'daily')
            ->value('used'));
    }

    public function test_webhook_endpoint_creation_is_blocked_by_plan_count_limit(): void
    {
        $user = $this->actingAsWithPermissions(['chat.webhooks.create', 'chat.webhooks.manage']);
        $this->createPlanWithChatLimits([
            'chat.webhook_endpoints.count' => ['value' => 1, 'period' => 'none'],
        ]);

        $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'Primary Endpoint',
            'url' => 'https://example.test/chat/webhook',
            'events' => ['message.created'],
            'scopes' => ['chat.external.messages.send'],
        ])->assertCreated();

        $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'Second Endpoint',
            'url' => 'https://example.test/chat/webhook-2',
            'events' => ['message.created'],
            'scopes' => ['chat.external.messages.send'],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_limit_exceeded')
            ->assertJsonPath('errors.feature_key', 'chat.webhook_endpoints.count')
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.used', 1);

        $this->assertSame(1, ChatWebhookEndpoint::query()->where('created_by', $user->id)->count());
    }

    public function test_attachment_upload_is_counted_and_blocked_by_monthly_limit(): void
    {
        Storage::fake('local');
        config()->set('chat.attachments.disk', 'local');
        config()->set('chat.attachments.allowed_mimes', ['image/png']);
        config()->set('chat.attachments.max_size_kb', 128);

        $user = $this->actingAsWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.send',
            'chat.attachments.upload',
        ]);
        $plan = $this->createPlanWithChatLimits([
            'chat.attachments.monthly' => ['value' => 1, 'period' => 'monthly'],
        ]);

        $conversation = $this->makeConversation($user);
        $this->addParticipant($conversation, $user, ['can_attach' => true]);
        $message = $this->makeMessage($conversation, $user);

        $this->postJson("/api/v1/chat/messages/{$message->id}/attachments", [
            'file' => UploadedFile::fake()->create('proof.png', 10, 'image/png'),
        ])->assertCreated();

        $this->assertFeatureUsage($user, $plan, 'chat.attachments.monthly', 'monthly', 1);

        $this->postJson("/api/v1/chat/messages/{$message->id}/attachments", [
            'file' => UploadedFile::fake()->create('second.png', 10, 'image/png'),
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_limit_exceeded')
            ->assertJsonPath('errors.feature_key', 'chat.attachments.monthly');

        $this->assertSame(1, MessageAttachment::query()->where('message_id', $message->id)->count());
    }

    /**
     * @param array<string, array{value: int, period: string}> $limits
     */
    private function createPlanWithChatLimits(array $limits, string $slug = 'free'): Plan
    {
        $plan = match ($slug) {
            'basic' => Plan::factory()->basic()->create(['slug' => 'basic']),
            'pro' => Plan::factory()->pro()->create(['slug' => 'pro']),
            default => Plan::factory()->free()->create(['slug' => 'free']),
        };

        foreach ($limits as $featureKey => $config) {
            $period = $config['period'];
            PlanFeature::factory()->create([
                'plan_id' => $plan->id,
                'feature_key' => $featureKey,
                'value' => (string) $config['value'],
                'value_type' => 'integer',
                'period' => $period,
                'reset_policy' => match ($period) {
                    'daily' => 'calendar_day',
                    'monthly' => 'calendar_month',
                    default => 'none',
                },
            ]);
        }

        return $plan;
    }

    private function actingAsWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();
        $user->permissions()->sync($permissionIds);
        Sanctum::actingAs($user);

        return $user;
    }

    private function makeConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Paid Chat',
            'description' => null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'created_from_conversation_id' => null,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
            'history_import_from_message_id' => null,
            'history_import_from_at' => null,
            'last_message_id' => null,
            'last_message_at' => null,
            'metadata' => null,
        ]);
    }

    private function addParticipant(Conversation $conversation, User $user, array $overrides = []): ConversationParticipant
    {
        return ConversationParticipant::query()->create(array_merge([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'access_state' => 'full',
            'block_display_mode' => null,
            'can_invite' => false,
            'can_remove' => false,
            'can_send' => true,
            'can_attach' => true,
            'can_manage' => false,
            'can_moderate' => false,
            'history_visibility_mode' => 'full',
            'joined_at' => now(),
        ], $overrides));
    }

    private function makeMessage(Conversation $conversation, User $sender): Message
    {
        return Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'external_id' => null,
            'reply_to_message_id' => null,
            'type' => 'text',
            'body' => 'Message with attachment',
            'status' => 'sent',
            'is_imported' => false,
            'imported_from_conversation_id' => null,
            'imported_from_message_id' => null,
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'metadata' => null,
        ]);
    }

    private function createUsage(User $user, Plan $plan, string $featureKey, string $period, int $used, int $limit): FeatureUsage
    {
        $periodStart = $period === 'daily'
            ? now()->startOfDay()
            : now()->startOfMonth();

        $periodEnd = $period === 'daily'
            ? now()->endOfDay()
            : now()->endOfMonth();

        return FeatureUsage::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => null,
            'plan_id' => $plan->id,
            'feature_key' => $featureKey,
            'period' => $period,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'used' => $used,
            'limit_value' => $limit,
            'reset_at' => $period === 'daily'
                ? now()->addDay()->startOfDay()
                : now()->addMonthNoOverflow()->startOfMonth(),
        ]);
    }

    private function assertFeatureUsage(User $user, Plan $plan, string $featureKey, string $period, int $used): void
    {
        $this->assertDatabaseHas('feature_usages', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'feature_key' => $featureKey,
            'period' => $period,
            'used' => $used,
        ]);
    }
}
