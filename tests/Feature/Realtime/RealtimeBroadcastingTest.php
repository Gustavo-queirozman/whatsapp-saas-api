<?php

namespace Tests\Feature\Realtime;

use App\Events\ConversationAssigned;
use App\Events\ConversationUpdated;
use App\Events\InstanceStatusChanged;
use App\Events\MessageReceived;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Message;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Services\WhatsApp\WhatsappInstanceStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class RealtimeBroadcastingTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('evolution.base_url', 'https://evolution.test');
        config()->set('evolution.api_key', 'test-api-key');
        config()->set('evolution.default_integration', 'WHATSAPP-BAILEYS');
        config()->set('evolution.webhook_secret', 'webhook-secret');
        config()->set('evolution.webhook_secret_header', 'X-Evolution-Webhook-Secret');
        config()->set('evolution.webhook_log_channel', 'single');

        Http::preventStrayRequests();
    }

    public function test_it_registers_the_broadcast_auth_route_and_authorizes_company_conversation_channels(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-realtime-auth']);
        $foreignCompany = $this->createCompany(['slug' => 'company-realtime-auth-foreign']);
        $role = $this->createRole($company, 'agent', ['conversations.view']);
        $foreignRole = $this->createRole($foreignCompany, 'agent', ['conversations.view']);

        $this->attachUserToCompany($user, $company, $role);
        $this->attachUserToCompany($user, $foreignCompany, $foreignRole);
        Sanctum::actingAs($user);

        $route = app('router')->getRoutes()->match(
            Request::create('/api/v1/broadcasting/auth', 'POST')
        );

        $this->assertSame('api/v1/broadcasting/auth', $route->uri());

        $channelCallback = Broadcast::driver('log')->getChannels()
            ->get('companies.{companyId}.conversations');

        $this->assertIsCallable($channelCallback);

        request()->attributes->set('current_company_id', $company->id);
        app(CurrentCompany::class)->set($company);

        $this->assertTrue($channelCallback($user, $company->id));

        request()->attributes->set('current_company_id', $foreignCompany->id);
        app(CurrentCompany::class)->set($foreignCompany);

        $this->assertFalse($channelCallback($user, $company->id));
    }

    public function test_it_dispatches_message_and_conversation_events_for_an_inbound_webhook(): void
    {
        Event::fake([
            MessageReceived::class,
            ConversationUpdated::class,
        ]);

        $company = $this->createCompany(['slug' => 'company-realtime-webhook']);
        $this->createWorkspace($company, ['slug' => 'workspace-realtime-webhook']);
        $sector = $this->createSector($company, ['slug' => 'support-realtime-webhook']);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'canal_realtime',
            'phone_number' => '5511999998001',
            'status' => 'connected',
            'metadata' => [],
        ]);

        $response = $this->withHeader('X-Evolution-Webhook-Secret', 'webhook-secret')
            ->postJson('/api/webhooks/evolution', [
                'instance' => $instance->instance_name,
                'data' => [
                    'key' => [
                        'remoteJid' => '5511999998002@s.whatsapp.net',
                        'fromMe' => false,
                        'id' => 'msg-realtime-001',
                    ],
                    'pushName' => 'Cliente Tempo Real',
                    'message' => [
                        'conversation' => 'Mensagem recebida',
                    ],
                    'messageType' => 'conversation',
                    'messageTimestamp' => 1715385600,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        Event::assertDispatched(MessageReceived::class, function (MessageReceived $event): bool {
            return $event->message->external_id === 'msg-realtime-001'
                && $event->message->direction === Message::DIRECTION_INBOUND;
        });

        Event::assertDispatched(ConversationUpdated::class, function (ConversationUpdated $event): bool {
            return $event->conversation->status === Conversation::STATUS_WAITING
                && $event->conversation->messages_count === 1;
        });
    }

    public function test_it_dispatches_assignment_events_when_a_conversation_is_assigned(): void
    {
        Event::fake([
            ConversationAssigned::class,
            ConversationUpdated::class,
        ]);

        $admin = User::factory()->create();
        $attendant = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-realtime-assignment']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-realtime-assignment']);
        $sector = $this->createSector($company, ['slug' => 'support-realtime-assignment']);
        $adminRole = $this->createRole($company, 'admin', ['conversations.manage']);
        $attendantRole = $this->createRole($company, 'agent', ['conversations.view']);

        $this->attachUserToCompany($admin, $company, $adminRole);
        $this->attachUserToCompany($attendant, $company, $attendantRole);
        $this->attachUserToSector($sector, $attendant);
        Sanctum::actingAs($admin);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Atribuicao',
            'phone' => '5511999998101',
            'metadata' => [],
        ]);

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_WAITING,
            'last_message_at' => now(),
        ]);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/assign-user', $conversation->id), [
                'user_id' => $attendant->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        Event::assertDispatched(ConversationAssigned::class, function (ConversationAssigned $event) use ($attendant): bool {
            return $event->conversation->assigned_user_id === $attendant->id
                && $event->conversation->status === Conversation::STATUS_OPEN;
        });

        Event::assertDispatched(ConversationUpdated::class, function (ConversationUpdated $event) use ($attendant): bool {
            return $event->conversation->assigned_user_id === $attendant->id
                && $event->conversation->status === Conversation::STATUS_OPEN;
        });
    }

    public function test_it_dispatches_instance_status_changed_when_the_gateway_status_changes(): void
    {
        Event::fake([
            InstanceStatusChanged::class,
        ]);

        $company = $this->createCompany(['slug' => 'company-realtime-instance']);
        $sector = $this->createSector($company, ['slug' => 'support-realtime-instance']);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'instancia_realtime',
            'phone_number' => '5511999998201',
            'status' => 'connecting',
            'metadata' => [],
        ]);

        app(WhatsappInstanceStatusService::class)->syncFromGateway($instance, [
            'instance' => [
                'state' => 'open',
            ],
        ]);

        Event::assertDispatched(InstanceStatusChanged::class, function (InstanceStatusChanged $event): bool {
            return $event->whatsappInstance->status === 'connected'
                && $event->whatsappInstance->last_connection_at !== null;
        });
    }
}
