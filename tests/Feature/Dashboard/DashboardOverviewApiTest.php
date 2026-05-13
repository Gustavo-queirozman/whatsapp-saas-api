<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class DashboardOverviewApiTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    public function test_it_returns_the_dashboard_overview_only_for_the_current_company(): void
    {
        CarbonImmutable::setTestNow('2026-05-12 15:00:00');

        $admin = User::factory()->create();
        $attendantOne = User::factory()->create(['name' => 'Atendente Um']);
        $attendantTwo = User::factory()->create(['name' => 'Atendente Dois']);
        $foreignUser = User::factory()->create();

        $company = $this->createCompany(['slug' => 'dashboard-company']);
        $foreignCompany = $this->createCompany(['slug' => 'dashboard-company-foreign']);
        $workspace = $this->createWorkspace($company, ['slug' => 'dashboard-workspace']);
        $foreignWorkspace = $this->createWorkspace($foreignCompany, ['slug' => 'dashboard-workspace-foreign']);
        $supportSector = $this->createSector($company, ['name' => 'Suporte', 'slug' => 'support-dashboard']);
        $salesSector = $this->createSector($company, ['name' => 'Comercial', 'slug' => 'sales-dashboard']);
        $foreignSector = $this->createSector($foreignCompany, ['name' => 'Oculto', 'slug' => 'hidden-dashboard']);

        $role = $this->createRole($company, 'admin', ['conversations.view']);
        $attendantRole = $this->createRole($company, 'agent', ['conversations.view']);
        $foreignRole = $this->createRole($foreignCompany, 'agent', ['conversations.view']);

        $this->attachUserToCompany($admin, $company, $role);
        $this->attachUserToCompany($attendantOne, $company, $attendantRole);
        $this->attachUserToCompany($attendantTwo, $company, $attendantRole);
        $this->attachUserToCompany($foreignUser, $foreignCompany, $foreignRole);

        Sanctum::actingAs($admin);

        $contactOne = $this->createContact($company, $workspace, ['phone' => '5511999999001']);
        $contactTwo = $this->createContact($company, $workspace, ['phone' => '5511999999002']);
        $contactThree = $this->createContact($company, $workspace, ['phone' => '5511999999003']);
        $contactFour = $this->createContact($company, $workspace, ['phone' => '5511999999004']);
        $foreignContact = $this->createContact($foreignCompany, $foreignWorkspace, ['phone' => '5511999999005']);

        $connectedInstance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $supportSector->id,
            'instance_name' => 'dashboard_connected',
            'phone_number' => '5511888881001',
            'status' => 'connected',
            'metadata' => [],
        ]);

        $disconnectedInstance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $salesSector->id,
            'instance_name' => 'dashboard_disconnected',
            'phone_number' => '5511888881002',
            'status' => 'disconnected',
            'metadata' => [],
        ]);

        WhatsappInstance::query()->create([
            'company_id' => $foreignCompany->id,
            'sector_id' => $foreignSector->id,
            'instance_name' => 'dashboard_foreign_connected',
            'phone_number' => '5511888881003',
            'status' => 'connected',
            'metadata' => [],
        ]);

        $todayOpenConversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $supportSector->id,
            'whatsapp_instance_id' => $connectedInstance->id,
            'contact_id' => $contactOne->id,
            'assigned_user_id' => $attendantOne->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_at' => now(),
            'last_message_at' => now(),
        ]);

        $todayWaitingConversation = Conversation::query()->create([
            'company_id' => $company->id,
            'sector_id' => $salesSector->id,
            'whatsapp_instance_id' => $disconnectedInstance->id,
            'contact_id' => $contactTwo->id,
            'status' => Conversation::STATUS_WAITING,
            'last_message_at' => now(),
        ]);

        $oldOpenConversation = Conversation::query()->forceCreate([
            'company_id' => $company->id,
            'sector_id' => $salesSector->id,
            'whatsapp_instance_id' => $disconnectedInstance->id,
            'contact_id' => $contactThree->id,
            'assigned_user_id' => $attendantOne->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_at' => CarbonImmutable::parse('2026-05-11 09:00:00'),
            'last_message_at' => CarbonImmutable::parse('2026-05-11 09:30:00'),
            'created_at' => CarbonImmutable::parse('2026-05-11 09:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-05-11 09:30:00'),
        ]);

        $closedConversation = Conversation::query()->forceCreate([
            'company_id' => $company->id,
            'sector_id' => $supportSector->id,
            'whatsapp_instance_id' => $connectedInstance->id,
            'contact_id' => $contactFour->id,
            'assigned_user_id' => $attendantTwo->id,
            'status' => Conversation::STATUS_CLOSED,
            'assigned_at' => CarbonImmutable::parse('2026-05-11 08:00:00'),
            'closed_at' => CarbonImmutable::parse('2026-05-11 08:20:00'),
            'last_message_at' => CarbonImmutable::parse('2026-05-11 08:20:00'),
            'created_at' => CarbonImmutable::parse('2026-05-11 08:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-05-11 08:20:00'),
        ]);

        Conversation::query()->create([
            'company_id' => $foreignCompany->id,
            'sector_id' => $foreignSector->id,
            'contact_id' => $foreignContact->id,
            'assigned_user_id' => $foreignUser->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_at' => now(),
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'company_id' => $company->id,
            'conversation_id' => $todayOpenConversation->id,
            'direction' => Message::DIRECTION_INBOUND,
            'type' => Message::TYPE_TEXT,
            'external_id' => 'dashboard-msg-001',
            'body' => 'Primeiro contato do dia',
            'payload' => [],
            'sent_at' => CarbonImmutable::parse('2026-05-12 10:00:00'),
        ]);

        Message::query()->create([
            'company_id' => $company->id,
            'conversation_id' => $todayOpenConversation->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'type' => Message::TYPE_TEXT,
            'external_id' => 'dashboard-msg-002',
            'body' => 'Primeira resposta',
            'payload' => [],
            'sent_at' => CarbonImmutable::parse('2026-05-12 10:05:00'),
        ]);

        Message::query()->create([
            'company_id' => $company->id,
            'conversation_id' => $todayWaitingConversation->id,
            'direction' => Message::DIRECTION_INBOUND,
            'type' => Message::TYPE_TEXT,
            'external_id' => 'dashboard-msg-003',
            'body' => 'Aguardando atendimento',
            'payload' => [],
            'sent_at' => CarbonImmutable::parse('2026-05-12 11:00:00'),
        ]);

        Message::query()->forceCreate([
            'company_id' => $company->id,
            'conversation_id' => $oldOpenConversation->id,
            'direction' => Message::DIRECTION_INBOUND,
            'type' => Message::TYPE_TEXT,
            'external_id' => 'dashboard-msg-004',
            'body' => 'Mensagem antiga',
            'payload' => [],
            'sent_at' => CarbonImmutable::parse('2026-05-11 09:00:00'),
            'created_at' => CarbonImmutable::parse('2026-05-11 09:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-05-11 09:00:00'),
        ]);

        Message::query()->forceCreate([
            'company_id' => $company->id,
            'conversation_id' => $oldOpenConversation->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'type' => Message::TYPE_TEXT,
            'external_id' => 'dashboard-msg-005',
            'body' => 'Resposta antiga',
            'payload' => [],
            'sent_at' => CarbonImmutable::parse('2026-05-11 09:30:00'),
            'created_at' => CarbonImmutable::parse('2026-05-11 09:30:00'),
            'updated_at' => CarbonImmutable::parse('2026-05-11 09:30:00'),
        ]);

        Message::query()->create([
            'company_id' => $foreignCompany->id,
            'conversation_id' => Conversation::query()->where('company_id', $foreignCompany->id)->firstOrFail()->id,
            'direction' => Message::DIRECTION_INBOUND,
            'type' => Message::TYPE_TEXT,
            'external_id' => 'dashboard-msg-foreign',
            'body' => 'Mensagem estrangeira',
            'payload' => [],
            'sent_at' => CarbonImmutable::parse('2026-05-12 12:00:00'),
        ]);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/dashboard/overview');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversations_today', 2)
            ->assertJsonPath('data.messages_today', 3)
            ->assertJsonPath('data.open_conversations', 2)
            ->assertJsonPath('data.waiting_conversations', 1)
            ->assertJsonPath('data.closed_conversations', 1)
            ->assertJsonPath('data.connected_numbers', 1)
            ->assertJsonPath('data.average_first_response_time.seconds', 300)
            ->assertJsonPath('data.average_first_response_time.formatted', '00:05:00')
            ->assertJsonPath('data.average_first_response_time.conversations_count', 1)
            ->assertJsonCount(2, 'data.conversations_by_sector')
            ->assertJsonCount(2, 'data.conversations_by_attendant')
            ->assertJsonFragment([
                'sector_id' => $supportSector->id,
                'sector_name' => 'Suporte',
                'sector_slug' => 'support-dashboard',
                'total_conversations' => 2,
                'open_conversations' => 1,
                'waiting_conversations' => 0,
                'closed_conversations' => 1,
            ])
            ->assertJsonFragment([
                'sector_id' => $salesSector->id,
                'sector_name' => 'Comercial',
                'sector_slug' => 'sales-dashboard',
                'total_conversations' => 2,
                'open_conversations' => 1,
                'waiting_conversations' => 1,
                'closed_conversations' => 0,
            ])
            ->assertJsonFragment([
                'user_id' => $attendantOne->id,
                'user_name' => 'Atendente Um',
                'total_conversations' => 2,
                'open_conversations' => 2,
                'waiting_conversations' => 0,
                'closed_conversations' => 0,
            ])
            ->assertJsonFragment([
                'user_id' => $attendantTwo->id,
                'user_name' => 'Atendente Dois',
                'total_conversations' => 1,
                'open_conversations' => 0,
                'waiting_conversations' => 0,
                'closed_conversations' => 1,
            ]);

        CarbonImmutable::setTestNow();
    }
}
