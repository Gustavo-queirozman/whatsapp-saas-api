<?php

namespace Tests\Feature\Ai;

use App\Domain\Ai\Models\AiUsage;
use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class AiApiTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'fake');
        config()->set('ai.conversation_history_limit', 20);
    }

    public function test_it_generates_a_conversation_summary_and_a_reply_suggestion_with_usage_logs(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-ai-summary']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-ai-summary']);
        $sector = $this->createSector($company, ['slug' => 'support-ai-summary']);
        $role = $this->createRole($company, 'agent', ['conversations.view', 'messages.view']);

        $this->attachUserToCompany($user, $company, $role);
        Sanctum::actingAs($user);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Paula Mendes',
            'phone' => '5511999997001',
            'metadata' => [],
        ]);

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_INBOUND,
            'type' => Message::TYPE_TEXT,
            'body' => 'Oi, estou com problema no acesso ao sistema.',
            'payload' => [],
            'sent_at' => now()->subMinutes(4),
        ]);

        Message::query()->create([
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'type' => Message::TYPE_TEXT,
            'body' => 'Pode me explicar o que aconteceu?',
            'payload' => [],
            'sent_at' => now()->subMinutes(3),
        ]);

        Message::query()->create([
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_INBOUND,
            'type' => Message::TYPE_TEXT,
            'body' => 'Depois da troca de senha nao consigo entrar.',
            'payload' => [],
            'sent_at' => now()->subMinutes(2),
        ]);

        $summaryResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/ai/summary', $conversation->id));

        $summaryResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.usage.provider', 'fake')
            ->assertJsonPath('data.usage.model', 'fake-summary-v1')
            ->assertJson(fn ($json) => $json->where('success', true)
                ->where('data.usage.provider', 'fake')
                ->where('data.usage.model', 'fake-summary-v1')
                ->whereType('data.summary', 'string')
                ->whereType('data.usage.id', 'integer')
                ->etc());

        $suggestionResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/ai/suggest-reply', $conversation->id));

        $suggestionResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.usage.provider', 'fake')
            ->assertJsonPath('data.usage.model', 'fake-suggest-v1')
            ->assertJson(fn ($json) => $json->where('success', true)
                ->where('data.usage.provider', 'fake')
                ->where('data.usage.model', 'fake-suggest-v1')
                ->whereType('data.suggested_reply', 'string')
                ->whereType('data.usage.id', 'integer')
                ->etc());

        $this->assertDatabaseHas('ai_usages', [
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'message_id' => null,
            'user_id' => $user->id,
            'provider' => 'fake',
            'operation' => 'summary',
            'status' => 'success',
            'model' => 'fake-summary-v1',
        ]);

        $this->assertDatabaseHas('ai_usages', [
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'message_id' => null,
            'user_id' => $user->id,
            'provider' => 'fake',
            'operation' => 'suggest_reply',
            'status' => 'success',
            'model' => 'fake-suggest-v1',
        ]);

        $this->assertSame(2, AiUsage::query()->count());
    }

    public function test_it_classifies_message_intent_and_logs_usage(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-ai-intent']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-ai-intent']);
        $sector = $this->createSector($company, ['slug' => 'finance-ai-intent']);
        $role = $this->createRole($company, 'agent', ['messages.view']);

        $this->attachUserToCompany($user, $company, $role);
        Sanctum::actingAs($user);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Ricardo Lima',
            'phone' => '5511999997002',
            'metadata' => [],
        ]);

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
        ]);

        $message = Message::query()->create([
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_INBOUND,
            'type' => Message::TYPE_TEXT,
            'body' => 'Preciso da segunda via do boleto da minha assinatura.',
            'payload' => [],
            'sent_at' => now(),
        ]);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/messages/%d/ai/classify-intent', $message->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent', 'financeiro')
            ->assertJsonPath('data.usage.provider', 'fake')
            ->assertJsonPath('data.usage.model', 'fake-classifier-v1');

        $this->assertDatabaseHas('ai_usages', [
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'user_id' => $user->id,
            'provider' => 'fake',
            'operation' => 'classify_intent',
            'status' => 'success',
            'model' => 'fake-classifier-v1',
            'result' => 'financeiro',
        ]);
    }

    public function test_it_respects_multi_tenancy_on_ai_endpoints(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-ai-visible']);
        $foreignCompany = $this->createCompany(['slug' => 'company-ai-hidden']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-ai-visible']);
        $foreignWorkspace = $this->createWorkspace($foreignCompany, ['slug' => 'workspace-ai-hidden']);
        $sector = $this->createSector($company, ['slug' => 'sector-ai-visible']);
        $foreignSector = $this->createSector($foreignCompany, ['slug' => 'sector-ai-hidden']);
        $role = $this->createRole($company, 'agent', ['conversations.view', 'messages.view']);

        $this->attachUserToCompany($user, $company, $role);
        Sanctum::actingAs($user);

        $foreignContact = Contact::query()->create([
            'company_id' => $foreignCompany->id,
            'workspace_id' => $foreignWorkspace->id,
            'name' => 'Contato Oculto',
            'phone' => '5511999997003',
            'metadata' => [],
        ]);

        $foreignConversation = Conversation::query()->create([
            'company_id' => $foreignCompany->id,
            'sector_id' => $foreignSector->id,
            'contact_id' => $foreignContact->id,
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
        ]);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/ai/summary', $foreignConversation->id));

        $response->assertForbidden();

        $this->assertDatabaseCount('ai_usages', 0);
    }
}
