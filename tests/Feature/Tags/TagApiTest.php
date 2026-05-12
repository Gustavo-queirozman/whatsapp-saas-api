<?php

namespace Tests\Feature\Tags;

use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Tags\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    public function test_it_can_crud_tags_only_within_the_current_company(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-tags']);
        $foreignCompany = $this->createCompany(['slug' => 'company-tags-foreign']);
        $role = $this->createRole($company, 'manager', ['tags.view', 'tags.manage']);
        $foreignRole = $this->createRole($foreignCompany, 'manager', ['tags.view', 'tags.manage']);

        $this->attachUserToCompany($user, $company, $role);
        $this->attachUserToCompany($user, $foreignCompany, $foreignRole);
        Sanctum::actingAs($user);

        $visibleTag = Tag::query()->create([
            'company_id' => $company->id,
            'name' => 'VIP',
            'color' => '#22C55E',
        ]);

        $hiddenTag = Tag::query()->create([
            'company_id' => $foreignCompany->id,
            'name' => 'Oculta',
            'color' => '#EF4444',
        ]);

        $listResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/tags');

        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleTag->id)
            ->assertJsonMissing(['id' => $hiddenTag->id]);

        $storeResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/tags', [
                'company_id' => $foreignCompany->id,
                'name' => 'Urgente',
                'color' => '#F97316',
            ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Urgente')
            ->assertJsonPath('data.company_id', $company->id);

        $createdTagId = (int) $storeResponse->json('data.id');

        $this->assertDatabaseHas('tags', [
            'id' => $createdTagId,
            'company_id' => $company->id,
            'name' => 'Urgente',
            'color' => '#F97316',
        ]);

        $showResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/tags/%d', $visibleTag->id));

        $showResponse->assertOk()
            ->assertJsonPath('data.id', $visibleTag->id)
            ->assertJsonPath('data.name', 'VIP');

        $updateResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson(sprintf('/api/v1/tags/%d', $visibleTag->id), [
                'company_id' => $foreignCompany->id,
                'name' => 'Premium',
                'color' => '#0EA5E9',
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $visibleTag->id)
            ->assertJsonPath('data.name', 'Premium')
            ->assertJsonPath('data.company_id', $company->id);

        $this->assertDatabaseHas('tags', [
            'id' => $visibleTag->id,
            'company_id' => $company->id,
            'name' => 'Premium',
            'color' => '#0EA5E9',
        ]);

        $hiddenResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/tags/%d', $hiddenTag->id));

        $hiddenResponse->assertNotFound();

        $destroyResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/tags/%d', $visibleTag->id));

        $destroyResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('tags', [
            'id' => $visibleTag->id,
        ]);
    }

    public function test_it_attaches_and_detaches_tags_for_contacts_and_conversations_with_company_isolation(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-tag-relations']);
        $foreignCompany = $this->createCompany(['slug' => 'company-tag-relations-foreign']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-tag-relations']);
        $foreignWorkspace = $this->createWorkspace($foreignCompany, ['slug' => 'workspace-tag-relations-foreign']);
        $sector = $this->createSector($company, ['slug' => 'sector-tag-relations']);
        $foreignSector = $this->createSector($foreignCompany, ['slug' => 'sector-tag-relations-foreign']);
        $role = $this->createRole($company, 'owner', ['contacts.manage', 'conversations.manage', 'tags.manage']);
        $foreignRole = $this->createRole($foreignCompany, 'owner', ['contacts.manage', 'conversations.manage', 'tags.manage']);

        $this->attachUserToCompany($user, $company, $role);
        $this->attachUserToCompany($user, $foreignCompany, $foreignRole);
        Sanctum::actingAs($user);

        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Contato Tag',
            'phone' => '5511999997001',
            'metadata' => [],
        ]);

        $foreignContact = Contact::query()->create([
            'company_id' => $foreignCompany->id,
            'workspace_id' => $foreignWorkspace->id,
            'name' => 'Contato Estrangeiro',
            'phone' => '5511999997002',
            'metadata' => [],
        ]);

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_WAITING,
        ]);

        $foreignConversation = Conversation::query()->create([
            'company_id' => $foreignCompany->id,
            'sector_id' => $foreignSector->id,
            'contact_id' => $foreignContact->id,
            'status' => Conversation::STATUS_WAITING,
        ]);

        $tag = Tag::query()->create([
            'company_id' => $company->id,
            'name' => 'Retorno',
            'color' => '#A855F7',
        ]);

        $foreignTag = Tag::query()->create([
            'company_id' => $foreignCompany->id,
            'name' => 'NaoExibir',
            'color' => '#DC2626',
        ]);

        $attachContactResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/contacts/%d/tags', $contact->id), [
                'tag_id' => $tag->id,
            ]);

        $attachContactResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tag->id);

        $this->assertDatabaseHas('contact_tag', [
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'tag_id' => $tag->id,
        ]);

        $duplicateAttachContactResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/contacts/%d/tags', $contact->id), [
                'tag_id' => $tag->id,
            ]);

        $duplicateAttachContactResponse->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame(1, Contact::query()->findOrFail($contact->id)->tags()->count());

        $attachConversationResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/tags', $conversation->id), [
                'tag_id' => $tag->id,
            ]);

        $attachConversationResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tag->id);

        $this->assertDatabaseHas('conversation_tag', [
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'tag_id' => $tag->id,
        ]);

        $foreignAttachResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/contacts/%d/tags', $contact->id), [
                'tag_id' => $foreignTag->id,
            ]);

        $foreignAttachResponse->assertUnprocessable();

        $foreignConversationResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/tags', $foreignConversation->id), [
                'tag_id' => $tag->id,
            ]);

        $foreignConversationResponse->assertNotFound();

        $detachContactResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/contacts/%d/tags/%d', $contact->id, $tag->id));

        $detachContactResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseMissing('contact_tag', [
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'tag_id' => $tag->id,
        ]);

        $detachConversationResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/conversations/%d/tags/%d', $conversation->id, $tag->id));

        $detachConversationResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseMissing('conversation_tag', [
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'tag_id' => $tag->id,
        ]);
    }
}
