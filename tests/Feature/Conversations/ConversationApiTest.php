<?php

namespace Tests\Feature\Conversations;

use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('evolution.base_url', 'https://evolution.test');
        config()->set('evolution.api_key', 'test-api-key');
        config()->set('evolution.default_integration', 'WHATSAPP-BAILEYS');
        config()->set('evolution.timeout', 15);

        Http::preventStrayRequests();
    }

    public function test_it_lists_shows_and_loads_messages_only_for_the_current_company(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-conversations']);
        $foreignCompany = $this->createCompany(['slug' => 'company-conversations-foreign']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-conversations']);
        $foreignWorkspace = $this->createWorkspace($foreignCompany, ['slug' => 'workspace-conversations-foreign']);
        $sector = $this->createSector($company, ['slug' => 'support-conversations']);
        $foreignSector = $this->createSector($foreignCompany, ['slug' => 'support-conversations-foreign']);
        $role = $this->createRole($company, 'agent', ['conversations.view', 'messages.view']);

        $this->attachUserToCompany($user, $company, $role);
        $this->attachUserToSector($sector, $user);
        Sanctum::actingAs($user);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Maria Silva',
            'phone' => '5511999990001',
            'metadata' => [],
        ]);

        $foreignContact = Contact::query()->create([
            'company_id' => $foreignCompany->id,
            'workspace_id' => $foreignWorkspace->id,
            'name' => 'Contato Oculto',
            'phone' => '5511999990002',
            'metadata' => [],
        ]);

        $visibleConversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_WAITING,
            'last_message_at' => now(),
        ]);

        $hiddenConversation = Conversation::query()->create([
            'company_id' => $foreignCompany->id,
            'sector_id' => $foreignSector->id,
            'contact_id' => $foreignContact->id,
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
        ]);

        $message = Message::query()->create([
            'company_id' => $company->id,
            'conversation_id' => $visibleConversation->id,
            'direction' => Message::DIRECTION_INBOUND,
            'type' => Message::TYPE_TEXT,
            'external_id' => 'msg-visible-001',
            'body' => 'Primeira mensagem',
            'payload' => [],
            'sent_at' => now(),
        ]);

        Message::query()->create([
            'company_id' => $foreignCompany->id,
            'conversation_id' => $hiddenConversation->id,
            'direction' => Message::DIRECTION_INBOUND,
            'type' => Message::TYPE_TEXT,
            'external_id' => 'msg-hidden-001',
            'body' => 'Mensagem oculta',
            'payload' => [],
            'sent_at' => now(),
        ]);

        $listResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/conversations?status=waiting&search=Maria');

        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleConversation->id)
            ->assertJsonPath('data.0.contact.name', 'Maria Silva')
            ->assertJsonPath('data.0.assigned_at', null)
            ->assertJsonPath('data.0.closed_at', null)
            ->assertJsonPath('data.0.messages_count', 1);

        $showResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/conversations/%d', $visibleConversation->id));

        $showResponse->assertOk()
            ->assertJsonPath('data.id', $visibleConversation->id)
            ->assertJsonPath('data.sector.slug', 'support-conversations');

        $messagesResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/conversations/%d/messages', $visibleConversation->id));

        $messagesResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $message->id)
            ->assertJsonPath('data.0.body', 'Primeira mensagem');

        $hiddenResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/conversations/%d', $hiddenConversation->id));

        $hiddenResponse->assertNotFound();
    }

    public function test_it_sends_an_outbound_message_using_evolution_and_reopens_the_conversation(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-send-message']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-send-message']);
        $sector = $this->createSector($company, ['slug' => 'support-send-message']);
        $role = $this->createRole($company, 'owner', ['conversations.manage', 'messages.manage']);

        $this->attachUserToCompany($user, $company, $role);
        Sanctum::actingAs($user);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Carlos Souza',
            'phone' => '5511999991111',
            'metadata' => [],
        ]);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'canal_suporte',
            'phone_number' => '5511888881111',
            'status' => 'connected',
            'metadata' => [],
        ]);

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'whatsapp_instance_id' => $instance->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_CLOSED,
        ]);

        Http::fake([
            'https://evolution.test/message/sendText/canal_suporte' => Http::response([
                'key' => [
                    'id' => 'outbound-msg-001',
                ],
                'status' => 'PENDING',
                'messageTimestamp' => 1715385600,
                'message' => [
                    'extendedTextMessage' => [
                        'text' => 'Retornando seu atendimento.',
                    ],
                ],
            ], 201),
        ]);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/send-message', $conversation->id), [
                'body' => 'Retornando seu atendimento.',
                'options' => [
                    'delay' => 250,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversation.id', $conversation->id)
            ->assertJsonPath('data.conversation.status', Conversation::STATUS_OPEN)
            ->assertJsonPath('data.conversation.closed_at', null)
            ->assertJsonPath('data.message.direction', Message::DIRECTION_OUTBOUND)
            ->assertJsonPath('data.message.external_id', 'outbound-msg-001')
            ->assertJsonPath('data.message.body', 'Retornando seu atendimento.');

        $conversation->refresh();

        $this->assertSame(Conversation::STATUS_OPEN, $conversation->status);
        $this->assertNotNull($conversation->last_message_at);

        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'type' => Message::TYPE_TEXT,
            'external_id' => 'outbound-msg-001',
            'body' => 'Retornando seu atendimento.',
        ]);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://evolution.test/message/sendText/canal_suporte'
                && $request->hasHeader('apikey', 'test-api-key')
                && $request['number'] === '5511999991111'
                && $request['textMessage']['text'] === 'Retornando seu atendimento.'
                && $request['options']['delay'] === 250;
        });
    }

    public function test_it_closes_and_reopens_a_conversation(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-status-flow']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-status-flow']);
        $sector = $this->createSector($company, ['slug' => 'support-status-flow']);
        $role = $this->createRole($company, 'owner', ['conversations.manage']);

        $this->attachUserToCompany($user, $company, $role);
        Sanctum::actingAs($user);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Fluxo',
            'phone' => '5511999992222',
            'metadata' => [],
        ]);

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_WAITING,
        ]);

        $closeResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/close', $conversation->id));

        $closeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Conversation::STATUS_CLOSED)
            ->assertJson(fn ($json) => $json->where('success', true)
                ->where('data.status', Conversation::STATUS_CLOSED)
                ->whereType('data.closed_at', 'string')
                ->etc());

        $reopenResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/reopen', $conversation->id));

        $reopenResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Conversation::STATUS_OPEN)
            ->assertJsonPath('data.closed_at', null);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'status' => Conversation::STATUS_OPEN,
            'closed_at' => null,
        ]);
    }

    public function test_an_attendant_can_assign_a_waiting_conversation_to_themselves(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-assign-me']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-assign-me']);
        $sector = $this->createSector($company, ['slug' => 'support-assign-me']);
        $role = $this->createRole($company, 'agent', ['conversations.view']);

        $this->attachUserToCompany($user, $company, $role);
        $this->attachUserToSector($sector, $user);
        Sanctum::actingAs($user);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Queue',
            'phone' => '5511999993333',
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
            ->postJson(sprintf('/api/v1/conversations/%d/assign-me', $conversation->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonPath('data.status', Conversation::STATUS_OPEN)
            ->assertJsonPath('data.assigned_user_id', $user->id)
            ->assertJson(fn ($json) => $json->where('success', true)
                ->where('data.id', $conversation->id)
                ->where('data.status', Conversation::STATUS_OPEN)
                ->where('data.assigned_user_id', $user->id)
                ->whereType('data.assigned_at', 'string')
                ->where('data.closed_at', null)
                ->etc());

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'assigned_user_id' => $user->id,
            'status' => Conversation::STATUS_OPEN,
        ]);
    }

    public function test_it_lists_attendants_and_the_waiting_queue_only_for_the_current_company(): void
    {
        $admin = User::factory()->create();
        $attendant = User::factory()->create();
        $inactiveAttendant = User::factory()->create();
        $viewerWithoutConversationAccess = User::factory()->create();
        $foreignAttendant = User::factory()->create();

        $company = $this->createCompany(['slug' => 'company-queue']);
        $foreignCompany = $this->createCompany(['slug' => 'company-queue-foreign']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-queue']);
        $foreignWorkspace = $this->createWorkspace($foreignCompany, ['slug' => 'workspace-queue-foreign']);
        $support = $this->createSector($company, ['slug' => 'support-queue']);
        $sales = $this->createSector($company, ['slug' => 'sales-queue']);
        $foreignSector = $this->createSector($foreignCompany, ['slug' => 'foreign-queue']);

        $adminRole = $this->createRole($company, 'admin', ['conversations.manage']);
        $attendantRole = $this->createRole($company, 'agent', ['conversations.view']);
        $noConversationRole = $this->createRole($company, 'observer', ['messages.view']);
        $foreignRole = $this->createRole($foreignCompany, 'agent', ['conversations.view']);

        $this->attachUserToCompany($admin, $company, $adminRole);
        $this->attachUserToCompany($attendant, $company, $attendantRole);
        $this->attachUserToCompany($inactiveAttendant, $company, $attendantRole, false);
        $this->attachUserToCompany($viewerWithoutConversationAccess, $company, $noConversationRole);
        $this->attachUserToCompany($foreignAttendant, $foreignCompany, $foreignRole);

        Sanctum::actingAs($admin);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Waiting',
            'phone' => '5511999994444',
            'metadata' => [],
        ]);

        $secondContact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Open',
            'phone' => '5511999994445',
            'metadata' => [],
        ]);

        $foreignContact = Contact::query()->create([
            'company_id' => $foreignCompany->id,
            'workspace_id' => $foreignWorkspace->id,
            'name' => 'Cliente Foreign',
            'phone' => '5511999994446',
            'metadata' => [],
        ]);

        $waitingConversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $support->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_WAITING,
            'last_message_at' => now(),
        ]);

        Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sales->id,
            'contact_id' => $secondContact->id,
            'assigned_user_id' => $attendant->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_at' => now(),
            'last_message_at' => now(),
        ]);

        Conversation::query()->create([
            'company_id' => $foreignCompany->id,
            'sector_id' => $foreignSector->id,
            'contact_id' => $foreignContact->id,
            'status' => Conversation::STATUS_WAITING,
            'last_message_at' => now(),
        ]);

        $attendantsResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/attendants');

        $attendantsResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['user_id' => $admin->id])
            ->assertJsonFragment(['user_id' => $attendant->id]);

        $queueResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/queue');

        $queueResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.waiting', 1)
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.summary.closed', 0)
            ->assertJsonCount(1, 'data.conversations')
            ->assertJsonPath('data.conversations.0.id', $waitingConversation->id);
    }

    public function test_admin_can_assign_a_conversation_to_another_attendant_and_transfer_it_to_another_sector(): void
    {
        $admin = User::factory()->create();
        $attendant = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-admin-transfer']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-admin-transfer']);
        $support = $this->createSector($company, ['slug' => 'support-admin-transfer']);
        $billing = $this->createSector($company, ['slug' => 'billing-admin-transfer']);
        $adminRole = $this->createRole($company, 'admin', ['conversations.manage']);
        $attendantRole = $this->createRole($company, 'agent', ['conversations.view']);

        $this->attachUserToCompany($admin, $company, $adminRole);
        $this->attachUserToCompany($attendant, $company, $attendantRole);
        $this->attachUserToSector($support, $attendant);
        Sanctum::actingAs($admin);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Transferencia',
            'phone' => '5511999995555',
            'metadata' => [],
        ]);

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $support->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_WAITING,
            'last_message_at' => now(),
        ]);

        $assignResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/assign-user', $conversation->id), [
                'user_id' => $attendant->id,
            ]);

        $assignResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Conversation::STATUS_OPEN)
            ->assertJsonPath('data.assigned_user_id', $attendant->id);

        $transferResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/transfer-sector', $conversation->id), [
                'sector_id' => $billing->id,
            ]);

        $transferResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Conversation::STATUS_WAITING)
            ->assertJsonPath('data.sector_id', $billing->id)
            ->assertJsonPath('data.assigned_user_id', null)
            ->assertJsonPath('data.assigned_at', null);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'sector_id' => $billing->id,
            'status' => Conversation::STATUS_WAITING,
            'assigned_user_id' => null,
        ]);
    }

    public function test_an_attendant_cannot_transfer_a_conversation_to_another_sector(): void
    {
        $attendant = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-transfer-forbidden']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-transfer-forbidden']);
        $support = $this->createSector($company, ['slug' => 'support-transfer-forbidden']);
        $billing = $this->createSector($company, ['slug' => 'billing-transfer-forbidden']);
        $attendantRole = $this->createRole($company, 'agent', ['conversations.view']);

        $this->attachUserToCompany($attendant, $company, $attendantRole);
        Sanctum::actingAs($attendant);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Restrito',
            'phone' => '5511999996666',
            'metadata' => [],
        ]);

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $support->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_user_id' => $attendant->id,
            'assigned_at' => now(),
            'last_message_at' => now(),
        ]);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/transfer-sector', $conversation->id), [
                'sector_id' => $billing->id,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'sector_id' => $support->id,
            'assigned_user_id' => $attendant->id,
            'status' => Conversation::STATUS_OPEN,
        ]);
    }
}
