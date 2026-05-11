<?php

namespace Tests\Feature\WhatsApp;

use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
