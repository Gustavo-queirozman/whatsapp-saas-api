<?php

namespace Tests\Feature\Queues;

use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Queues\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class SectorApiTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    public function test_it_can_crud_sectors_and_manage_sector_users_with_company_isolation(): void
    {
        $manager = User::factory()->create();
        $attendant = User::factory()->create();
        $foreignUser = User::factory()->create();

        $company = $this->createCompany(['slug' => 'company-sectors']);
        $foreignCompany = $this->createCompany(['slug' => 'company-sectors-foreign']);

        $managerRole = $this->createRole($company, 'manager', ['sectors.view', 'sectors.manage']);
        $attendantRole = $this->createRole($company, 'agent', ['conversations.view']);
        $foreignRole = $this->createRole($foreignCompany, 'manager', ['sectors.view', 'sectors.manage']);

        $this->attachUserToCompany($manager, $company, $managerRole);
        $this->attachUserToCompany($attendant, $company, $attendantRole);
        $this->attachUserToCompany($foreignUser, $foreignCompany, $foreignRole);

        Sanctum::actingAs($manager);

        $visibleSector = $this->createSector($company, [
            'name' => 'Suporte',
            'slug' => 'suporte-sectors',
        ]);

        $hiddenSector = $this->createSector($foreignCompany, [
            'name' => 'Oculto',
            'slug' => 'oculto-sectors',
        ]);

        $listResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/sectors');

        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleSector->id)
            ->assertJsonMissing(['id' => $hiddenSector->id]);

        $storeResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/sectors', [
                'company_id' => $foreignCompany->id,
                'name' => 'Financeiro',
                'slug' => 'financeiro-sectors',
                'color' => '#F97316',
                'settings' => [
                    'auto_assign' => true,
                ],
            ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.name', 'Financeiro')
            ->assertJsonPath('data.settings.auto_assign', true);

        $createdSectorId = (int) $storeResponse->json('data.id');

        $this->assertDatabaseHas('sectors', [
            'id' => $createdSectorId,
            'company_id' => $company->id,
            'name' => 'Financeiro',
            'slug' => 'financeiro-sectors',
        ]);

        $showResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/sectors/%d', $visibleSector->id));

        $showResponse->assertOk()
            ->assertJsonPath('data.id', $visibleSector->id)
            ->assertJsonPath('data.name', 'Suporte');

        $updateResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson(sprintf('/api/v1/sectors/%d', $visibleSector->id), [
                'name' => 'Suporte VIP',
                'slug' => 'suporte-vip-sectors',
                'color' => '#0EA5E9',
                'settings' => [
                    'priority' => 'high',
                ],
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $visibleSector->id)
            ->assertJsonPath('data.name', 'Suporte VIP')
            ->assertJsonPath('data.settings.priority', 'high');

        $attachResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/sectors/%d/users', $visibleSector->id), [
                'user_id' => $attendant->id,
            ]);

        $attachResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users_count', 1)
            ->assertJsonPath('data.users.0.id', $attendant->id);

        $this->assertDatabaseHas('sector_users', [
            'company_id' => $company->id,
            'sector_id' => $visibleSector->id,
            'user_id' => $attendant->id,
        ]);

        $hiddenResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/sectors/%d', $hiddenSector->id));

        $hiddenResponse->assertNotFound();

        $detachResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/sectors/%d/users/%d', $visibleSector->id, $attendant->id));

        $detachResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users_count', 0);

        $this->assertDatabaseMissing('sector_users', [
            'company_id' => $company->id,
            'sector_id' => $visibleSector->id,
            'user_id' => $attendant->id,
        ]);

        $destroyResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/sectors/%d', $visibleSector->id));

        $destroyResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('sectors', [
            'id' => $visibleSector->id,
        ]);
    }

    public function test_it_lists_the_queue_by_sector_and_auto_assigns_the_lowest_load_attendant(): void
    {
        $manager = User::factory()->create();
        $busyAttendant = User::factory()->create();
        $availableAttendant = User::factory()->create();
        $foreignAttendant = User::factory()->create();

        $company = $this->createCompany(['slug' => 'company-sector-queue']);
        $foreignCompany = $this->createCompany(['slug' => 'company-sector-queue-foreign']);
        $workspace = $this->createWorkspace($company, ['slug' => 'workspace-sector-queue']);
        $foreignWorkspace = $this->createWorkspace($foreignCompany, ['slug' => 'workspace-sector-queue-foreign']);
        $support = $this->createSector($company, ['slug' => 'support-sector-queue']);
        $sales = $this->createSector($company, ['slug' => 'sales-sector-queue']);
        $foreignSector = $this->createSector($foreignCompany, ['slug' => 'foreign-sector-queue']);

        $managerRole = $this->createRole($company, 'manager', ['conversations.manage', 'sectors.view']);
        $attendantRole = $this->createRole($company, 'agent', ['conversations.view']);
        $foreignRole = $this->createRole($foreignCompany, 'agent', ['conversations.view']);

        $this->attachUserToCompany($manager, $company, $managerRole);
        $this->attachUserToCompany($busyAttendant, $company, $attendantRole);
        $this->attachUserToCompany($availableAttendant, $company, $attendantRole);
        $this->attachUserToCompany($foreignAttendant, $foreignCompany, $foreignRole);

        $this->attachUserToSector($support, $busyAttendant);
        $this->attachUserToSector($support, $availableAttendant);
        $this->attachUserToSector($foreignSector, $foreignAttendant);

        Sanctum::actingAs($manager);

        $waitingContact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Waiting',
            'phone' => '5511999998101',
            'metadata' => [],
        ]);

        $busyContact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Busy',
            'phone' => '5511999998102',
            'metadata' => [],
        ]);

        $salesContact = Contact::query()->create([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Sales',
            'phone' => '5511999998103',
            'metadata' => [],
        ]);

        $foreignContact = Contact::query()->create([
            'company_id' => $foreignCompany->id,
            'workspace_id' => $foreignWorkspace->id,
            'name' => 'Cliente Foreign',
            'phone' => '5511999998104',
            'metadata' => [],
        ]);

        $waitingConversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $support->id,
            'contact_id' => $waitingContact->id,
            'status' => Conversation::STATUS_WAITING,
            'last_message_at' => now(),
        ]);

        Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $support->id,
            'contact_id' => $busyContact->id,
            'assigned_user_id' => $busyAttendant->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_at' => now(),
            'last_message_at' => now(),
        ]);

        Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sales->id,
            'contact_id' => $salesContact->id,
            'assigned_user_id' => $busyAttendant->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_at' => now(),
            'last_message_at' => now(),
        ]);

        Conversation::query()->create([
            'company_id' => $foreignCompany->id,
            'sector_id' => $foreignSector->id,
            'contact_id' => $foreignContact->id,
            'assigned_user_id' => $foreignAttendant->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_at' => now(),
            'last_message_at' => now(),
        ]);

        $queueResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/sectors/%d/queue', $support->id));

        $queueResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sector.id', $support->id)
            ->assertJsonPath('data.summary.waiting', 1)
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.summary.closed', 0)
            ->assertJsonCount(1, 'data.conversations')
            ->assertJsonPath('data.conversations.0.id', $waitingConversation->id);

        $assignResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/conversations/%d/auto-assign', $waitingConversation->id));

        $assignResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $waitingConversation->id)
            ->assertJsonPath('data.status', Conversation::STATUS_OPEN)
            ->assertJsonPath('data.assigned_user_id', $availableAttendant->id);

        $this->assertDatabaseHas('conversations', [
            'id' => $waitingConversation->id,
            'assigned_user_id' => $availableAttendant->id,
            'status' => Conversation::STATUS_OPEN,
        ]);
    }
}
