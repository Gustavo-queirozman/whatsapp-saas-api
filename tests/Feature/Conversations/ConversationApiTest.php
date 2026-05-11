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
            ->assertJsonPath('data.status', Conversation::STATUS_CLOSED);

        $reopenResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/reopen', $conversation->id));

        $reopenResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Conversation::STATUS_OPEN);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'status' => Conversation::STATUS_OPEN,
        ]);
    }
}
