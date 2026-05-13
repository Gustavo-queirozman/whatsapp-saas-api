<?php

namespace Tests\Feature\WhatsApp;

use App\Domain\Chatbot\Models\BotFlow;
use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class EvolutionWebhookApiTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('evolution.webhook_secret', 'webhook-secret');
        config()->set('evolution.webhook_secret_header', 'X-Evolution-Webhook-Secret');
        config()->set('evolution.webhook_log_channel', 'single');
        config()->set('evolution.base_url', 'https://evolution.test');
        config()->set('evolution.api_key', 'test-api-key');

        Http::preventStrayRequests();
    }

    public function test_it_processes_an_inbound_evolution_webhook_and_persists_contact_conversation_and_message(): void
    {
        $company = $this->createCompany(['slug' => 'company-webhook']);
        $workspace = $this->createWorkspace($company, ['slug' => 'principal-webhook']);
        $sector = $this->createSector($company, ['slug' => 'suporte-webhook']);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'canal_principal',
            'phone_number' => '5511999999998',
            'status' => 'connected',
            'metadata' => [],
        ]);

        $payload = [
            'event' => 'MESSAGES_UPSERT',
            'instance' => $instance->instance_name,
            'data' => [
                'key' => [
                    'remoteJid' => '5511999999999@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => 'msg-ext-001',
                ],
                'pushName' => 'Maria Souza',
                'message' => [
                    'conversation' => 'Ola, preciso de ajuda',
                ],
                'messageType' => 'conversation',
                'messageTimestamp' => 1715385600,
            ],
        ];

        $response = $this->withHeader('X-Evolution-Webhook-Secret', 'webhook-secret')
            ->postJson('/api/webhooks/evolution', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'processed');

        $contact = Contact::query()->first();
        $conversation = Conversation::query()->first();
        $message = Message::query()->first();

        $this->assertNotNull($contact);
        $this->assertNotNull($conversation);
        $this->assertNotNull($message);

        $this->assertSame($company->id, $contact->company_id);
        $this->assertSame($workspace->id, $contact->workspace_id);
        $this->assertSame('5511999999999', $contact->phone);
        $this->assertSame('Maria Souza', $contact->name);

        $this->assertSame($company->id, $conversation->company_id);
        $this->assertSame($sector->id, $conversation->sector_id);
        $this->assertSame($instance->id, $conversation->whatsapp_instance_id);
        $this->assertSame($contact->id, $conversation->contact_id);
        $this->assertSame(Conversation::STATUS_WAITING, $conversation->status);
        $this->assertNotNull($conversation->last_message_at);

        $this->assertSame($company->id, $message->company_id);
        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertSame('inbound', $message->direction);
        $this->assertSame('text', $message->type);
        $this->assertSame('msg-ext-001', $message->external_id);
        $this->assertSame('Ola, preciso de ajuda', $message->body);
    }

    public function test_it_ignores_messages_sent_by_the_instance_itself(): void
    {
        $company = $this->createCompany(['slug' => 'company-ignore']);
        $this->createWorkspace($company, ['slug' => 'principal-ignore']);
        $sector = $this->createSector($company, ['slug' => 'suporte-ignore']);

        WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'instancia_ignore',
            'phone_number' => '5511888888888',
            'status' => 'connected',
            'metadata' => [],
        ]);

        $response = $this->withHeader('X-Evolution-Webhook-Secret', 'webhook-secret')
            ->postJson('/api/webhooks/evolution', [
                'instance' => 'instancia_ignore',
                'data' => [
                    'key' => [
                        'remoteJid' => '5511999999999@s.whatsapp.net',
                        'fromMe' => true,
                        'id' => 'msg-ignore-001',
                    ],
                    'message' => [
                        'conversation' => 'Mensagem interna',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ignored');

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_it_does_not_duplicate_messages_by_external_id(): void
    {
        $company = $this->createCompany(['slug' => 'company-duplicate']);
        $workspace = $this->createWorkspace($company, ['slug' => 'principal-duplicate']);
        $sector = $this->createSector($company, ['slug' => 'suporte-duplicate']);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'instancia_duplicate',
            'phone_number' => '5511777777777',
            'status' => 'connected',
            'metadata' => [],
        ]);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Contato Existente',
            'phone' => '5511999999999',
            'metadata' => [],
        ]);

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'whatsapp_instance_id' => $instance->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
        ]);

        $message = Message::query()->create([
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'external_id' => 'msg-dup-001',
            'body' => 'Mensagem original',
            'payload' => [],
            'sent_at' => now(),
        ]);

        $response = $this->withHeader('X-Evolution-Webhook-Secret', 'webhook-secret')
            ->postJson('/api/webhooks/evolution', [
                'instance' => 'instancia_duplicate',
                'data' => [
                    'key' => [
                        'remoteJid' => '5511999999999@s.whatsapp.net',
                        'fromMe' => false,
                        'id' => 'msg-dup-001',
                    ],
                    'pushName' => 'Contato Existente',
                    'message' => [
                        'conversation' => 'Mensagem repetida',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'duplicate')
            ->assertJsonPath('data.message_id', $message->id);

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_it_rejects_the_webhook_when_the_secret_header_is_invalid(): void
    {
        $response = $this->withHeader('X-Evolution-Webhook-Secret', 'invalid-secret')
            ->postJson('/api/webhooks/evolution', [
                'instance' => 'inexistente',
            ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Token de webhook invalido.');
    }

    public function test_it_executes_the_active_chatbot_flow_and_sends_the_welcome_menu(): void
    {
        Http::fake([
            'https://evolution.test/message/sendText/canal_bot' => Http::response([
                'id' => 'bot-reply-001',
                'timestamp' => 1715385605,
                'status' => 'PENDING',
            ], 201),
        ]);

        $company = $this->createCompany(['slug' => 'company-chatbot-welcome']);
        $this->createWorkspace($company, ['slug' => 'workspace-chatbot-welcome']);
        $sector = $this->createSector($company, ['slug' => 'support-chatbot-welcome']);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'canal_bot',
            'phone_number' => '5511999997001',
            'status' => 'connected',
            'metadata' => [],
        ]);

        BotFlow::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'name' => 'Boas-vindas',
            'is_active' => true,
            'welcome_message' => 'Bem-vindo ao atendimento.',
            'menu_message' => "1. Suporte\n2. Financeiro",
        ])->options()->createMany([
            [
                'company_id' => $company->id,
                'label' => 'Suporte',
                'number' => '1',
                'keywords' => ['suporte'],
                'action' => 'reply',
                'response_message' => 'Vamos seguir por aqui.',
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'company_id' => $company->id,
                'label' => 'Financeiro',
                'number' => '2',
                'keywords' => ['financeiro'],
                'action' => 'reply',
                'response_message' => 'Transferindo para o financeiro.',
                'sort_order' => 1,
                'is_active' => true,
            ],
        ]);

        $response = $this->withHeader('X-Evolution-Webhook-Secret', 'webhook-secret')
            ->postJson('/api/webhooks/evolution', [
                'instance' => $instance->instance_name,
                'data' => [
                    'key' => [
                        'remoteJid' => '5511999997002@s.whatsapp.net',
                        'fromMe' => false,
                        'id' => 'msg-chatbot-001',
                    ],
                    'pushName' => 'Cliente Bot',
                    'message' => [
                        'conversation' => 'Oi',
                    ],
                    'messageType' => 'conversation',
                    'messageTimestamp' => 1715385600,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'processed');

        $conversation = Conversation::query()->firstOrFail();
        $outboundMessage = Message::query()
            ->where('direction', Message::DIRECTION_OUTBOUND)
            ->first();

        $this->assertNotNull($outboundMessage);
        $this->assertSame($conversation->id, $outboundMessage->conversation_id);
        $this->assertSame("Bem-vindo ao atendimento.\n\n1. Suporte\n2. Financeiro", $outboundMessage->body);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://evolution.test/message/sendText/canal_bot'
                && $request['number'] === '5511999997002'
                && $request['textMessage']['text'] === "Bem-vindo ao atendimento.\n\n1. Suporte\n2. Financeiro";
        });
    }

    public function test_it_transfers_the_conversation_sector_when_the_chatbot_option_matches(): void
    {
        Http::fake([
            'https://evolution.test/message/sendText/canal_transferencia' => Http::response([
                'id' => 'bot-reply-002',
                'timestamp' => 1715385610,
                'status' => 'PENDING',
            ], 201),
        ]);

        $company = $this->createCompany(['slug' => 'company-chatbot-transfer']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-chatbot-transfer']);
        $support = $this->createSector($company, ['slug' => 'support-chatbot-transfer']);
        $billing = $this->createSector($company, ['slug' => 'billing-chatbot-transfer']);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $support->id,
            'instance_name' => 'canal_transferencia',
            'phone_number' => '5511999997101',
            'status' => 'connected',
            'metadata' => [],
        ]);

        $flow = BotFlow::query()->create([
            'company_id' => $company->id,
            'sector_id' => $support->id,
            'name' => 'Transferencia',
            'is_active' => true,
        ]);

        $flow->options()->create([
            'company_id' => $company->id,
            'label' => 'Financeiro',
            'number' => '2',
            'keywords' => ['financeiro'],
            'action' => 'transfer_sector',
            'target_sector_id' => $billing->id,
            'response_message' => 'Encaminhando para o financeiro.',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Transferencia',
            'phone' => '5511999997102',
            'metadata' => [],
        ]);

        Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $support->id,
            'whatsapp_instance_id' => $instance->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_WAITING,
            'last_message_at' => now(),
        ]);

        $response = $this->withHeader('X-Evolution-Webhook-Secret', 'webhook-secret')
            ->postJson('/api/webhooks/evolution', [
                'instance' => $instance->instance_name,
                'data' => [
                    'key' => [
                        'remoteJid' => '5511999997102@s.whatsapp.net',
                        'fromMe' => false,
                        'id' => 'msg-chatbot-002',
                    ],
                    'pushName' => 'Cliente Transferencia',
                    'message' => [
                        'conversation' => '2',
                    ],
                    'messageType' => 'conversation',
                    'messageTimestamp' => 1715385600,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $conversation = Conversation::query()->firstOrFail();

        $this->assertSame($billing->id, $conversation->sector_id);
        $this->assertSame(Conversation::STATUS_WAITING, $conversation->status);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'body' => 'Encaminhando para o financeiro.',
        ]);
    }

    public function test_it_sends_the_out_of_hours_message_when_the_flow_is_outside_business_hours(): void
    {
        CarbonImmutable::setTestNow('2026-05-12 23:30:00');

        Http::fake([
            'https://evolution.test/message/sendText/canal_horario' => Http::response([
                'id' => 'bot-reply-003',
                'timestamp' => 1715385620,
                'status' => 'PENDING',
            ], 201),
        ]);

        $company = $this->createCompany(['slug' => 'company-chatbot-hours']);
        $this->createWorkspace($company, ['slug' => 'workspace-chatbot-hours']);
        $sector = $this->createSector($company, ['slug' => 'support-chatbot-hours']);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'canal_horario',
            'phone_number' => '5511999997201',
            'status' => 'connected',
            'metadata' => [],
        ]);

        BotFlow::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'name' => 'Horario comercial',
            'is_active' => true,
            'out_of_hours_message' => 'Estamos fora do horario comercial.',
            'office_hours_enabled' => true,
            'office_hours_timezone' => 'America/Sao_Paulo',
            'office_hours' => [
                'tuesday' => [
                    'enabled' => true,
                    'start' => '08:00',
                    'end' => '18:00',
                ],
            ],
        ]);

        $response = $this->withHeader('X-Evolution-Webhook-Secret', 'webhook-secret')
            ->postJson('/api/webhooks/evolution', [
                'instance' => $instance->instance_name,
                'data' => [
                    'key' => [
                        'remoteJid' => '5511999997202@s.whatsapp.net',
                        'fromMe' => false,
                        'id' => 'msg-chatbot-003',
                    ],
                    'pushName' => 'Cliente Horario',
                    'message' => [
                        'conversation' => 'Ola',
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('messages', [
            'direction' => Message::DIRECTION_OUTBOUND,
            'body' => 'Estamos fora do horario comercial.',
        ]);

        CarbonImmutable::setTestNow();
    }

    public function test_it_does_not_execute_the_chatbot_when_the_conversation_is_already_assigned(): void
    {
        $company = $this->createCompany(['slug' => 'company-chatbot-assigned']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-chatbot-assigned']);
        $sector = $this->createSector($company, ['slug' => 'support-chatbot-assigned']);
        $user = User::factory()->create();

        $role = $this->createRole($company, 'agent-assigned', ['conversations.view']);
        $this->attachUserToCompany($user, $company, $role);
        $this->attachUserToSector($sector, $user);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'canal_atribuido',
            'phone_number' => '5511999997301',
            'status' => 'connected',
            'metadata' => [],
        ]);

        $flow = BotFlow::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'name' => 'Fluxo atribuido',
            'is_active' => true,
            'welcome_message' => 'Mensagem automatica',
        ]);

        $flow->options()->create([
            'company_id' => $company->id,
            'label' => 'Suporte',
            'number' => '1',
            'keywords' => ['suporte'],
            'action' => 'reply',
            'response_message' => 'Resposta automatica',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Atribuido',
            'phone' => '5511999997302',
            'metadata' => [],
        ]);

        Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'whatsapp_instance_id' => $instance->id,
            'contact_id' => $contact->id,
            'assigned_user_id' => $user->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_at' => now(),
            'last_message_at' => now(),
        ]);

        $response = $this->withHeader('X-Evolution-Webhook-Secret', 'webhook-secret')
            ->postJson('/api/webhooks/evolution', [
                'instance' => $instance->instance_name,
                'data' => [
                    'key' => [
                        'remoteJid' => '5511999997302@s.whatsapp.net',
                        'fromMe' => false,
                        'id' => 'msg-chatbot-004',
                    ],
                    'pushName' => 'Cliente Atribuido',
                    'message' => [
                        'conversation' => 'Preciso de ajuda',
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('messages', 1);
    }
}
