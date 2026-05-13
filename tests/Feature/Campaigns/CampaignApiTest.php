<?php

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignContact;
use App\Domain\Campaigns\Models\CampaignMessage;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Jobs\SendCampaignMessageJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class CampaignApiTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('evolution.base_url', 'https://evolution.test');
        config()->set('evolution.api_key', 'test-api-key');
        config()->set('evolution.timeout', 15);

        Http::preventStrayRequests();
    }

    public function test_it_can_crud_campaigns_and_import_contacts_with_company_isolation(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'campaign-company']);
        $foreignCompany = $this->createCompany(['slug' => 'campaign-company-foreign']);
        $sector = $this->createSector($company, ['slug' => 'campaign-sector']);
        $foreignSector = $this->createSector($foreignCompany, ['slug' => 'campaign-sector-foreign']);
        $instance = $this->createWhatsappInstance($company->id, $sector->id, 'campaign_instance');
        $foreignInstance = $this->createWhatsappInstance($foreignCompany->id, $foreignSector->id, 'campaign_instance_foreign');
        $role = $this->createRole($company, 'manager', ['campaigns.view', 'campaigns.manage']);
        $foreignRole = $this->createRole($foreignCompany, 'manager', ['campaigns.view', 'campaigns.manage']);

        $this->attachUserToCompany($user, $company, $role);
        $this->attachUserToCompany($user, $foreignCompany, $foreignRole);
        Sanctum::actingAs($user);

        $visibleCampaign = Campaign::query()->create([
            'company_id' => $company->id,
            'whatsapp_instance_id' => $instance->id,
            'name' => 'Campanha Visivel',
            'message' => 'Mensagem principal',
            'send_limit_per_minute' => 20,
            'status' => Campaign::STATUS_DRAFT,
        ]);

        $hiddenCampaign = Campaign::query()->create([
            'company_id' => $foreignCompany->id,
            'whatsapp_instance_id' => $foreignInstance->id,
            'name' => 'Campanha Oculta',
            'message' => 'Mensagem oculta',
            'send_limit_per_minute' => 20,
            'status' => Campaign::STATUS_DRAFT,
        ]);

        $listResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/campaigns');

        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleCampaign->id)
            ->assertJsonMissing(['id' => $hiddenCampaign->id]);

        $invalidStoreResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/campaigns', [
                'whatsapp_instance_id' => $foreignInstance->id,
                'name' => 'Campanha Invalida',
                'message' => 'Teste',
                'send_limit_per_minute' => 10,
            ]);

        $invalidStoreResponse->assertStatus(422)
            ->assertJsonValidationErrors(['whatsapp_instance_id']);

        $storeResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/campaigns', [
                'company_id' => $foreignCompany->id,
                'whatsapp_instance_id' => $instance->id,
                'name' => 'Campanha Nova',
                'message' => 'Oferta ativa',
                'send_limit_per_minute' => 12,
            ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.whatsapp_instance_id', $instance->id)
            ->assertJsonPath('data.name', 'Campanha Nova')
            ->assertJsonPath('data.status', Campaign::STATUS_DRAFT);

        $campaignId = (int) $storeResponse->json('data.id');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaignId,
            'company_id' => $company->id,
            'whatsapp_instance_id' => $instance->id,
            'name' => 'Campanha Nova',
            'status' => Campaign::STATUS_DRAFT,
        ]);

        $importResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/campaigns/%d/contacts', $campaignId), [
                'contacts' => [
                    [
                        'name' => 'Maria',
                        'phone' => '+55 (11) 99999-0001',
                    ],
                    [
                        'name' => 'Joao',
                        'phone' => '5511999990002',
                    ],
                    [
                        'name' => 'Maria Atualizada',
                        'phone' => '5511999990001',
                    ],
                ],
            ]);

        $importResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('campaign_contacts', [
            'campaign_id' => $campaignId,
            'company_id' => $company->id,
            'phone' => '5511999990001',
            'name' => 'Maria Atualizada',
            'status' => CampaignContact::STATUS_PENDING,
        ]);

        $showResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/campaigns/%d', $campaignId));

        $showResponse->assertOk()
            ->assertJsonPath('data.id', $campaignId)
            ->assertJsonPath('data.total_contacts', 2)
            ->assertJsonPath('data.pending_contacts', 2);

        $invalidUpdateResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson(sprintf('/api/v1/campaigns/%d', $campaignId), [
                'whatsapp_instance_id' => $foreignInstance->id,
                'name' => 'Campanha Nova',
                'message' => 'Oferta atualizada',
                'send_limit_per_minute' => 15,
            ]);

        $invalidUpdateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['whatsapp_instance_id']);

        $updateResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson(sprintf('/api/v1/campaigns/%d', $campaignId), [
                'whatsapp_instance_id' => $instance->id,
                'name' => 'Campanha Premium',
                'message' => 'Oferta atualizada',
                'send_limit_per_minute' => 15,
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Campanha Premium')
            ->assertJsonPath('data.message', 'Oferta atualizada')
            ->assertJsonPath('data.send_limit_per_minute', 15);

        $hiddenResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/campaigns/%d', $hiddenCampaign->id));

        $hiddenResponse->assertNotFound();

        $destroyResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/campaigns/%d', $campaignId));

        $destroyResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Campanha removida com sucesso.');

        $this->assertDatabaseMissing('campaigns', [
            'id' => $campaignId,
        ]);
    }

    public function test_it_sends_campaign_messages_and_records_success_and_failure(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'campaign-send-company']);
        $sector = $this->createSector($company, ['slug' => 'campaign-send-sector']);
        $instance = $this->createWhatsappInstance($company->id, $sector->id, 'campaign_send_instance');
        $role = $this->createRole($company, 'manager', ['campaigns.view', 'campaigns.manage']);

        $this->attachUserToCompany($user, $company, $role);
        Sanctum::actingAs($user);

        Http::fake([
            'https://evolution.test/message/sendText/campaign_send_instance' => Http::sequence()
                ->push([
                    'id' => 'campaign-msg-001',
                    'timestamp' => 1715385600,
                    'status' => 'PENDING',
                ], 201)
                ->push([
                    'message' => 'Gateway error',
                ], 500),
        ]);

        $storeResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/campaigns', [
                'whatsapp_instance_id' => $instance->id,
                'name' => 'Campanha disparo',
                'message' => 'Mensagem de campanha',
                'send_limit_per_minute' => 60,
            ]);

        $campaignId = (int) $storeResponse->json('data.id');

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/campaigns/%d/contacts', $campaignId), [
                'contacts' => [
                    [
                        'name' => 'Contato 1',
                        'phone' => '5511999991001',
                    ],
                    [
                        'name' => 'Contato 2',
                        'phone' => '5511999991002',
                    ],
                ],
            ])
            ->assertOk();

        $scheduleResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/campaigns/%d/schedule', $campaignId), []);

        $scheduleResponse->assertOk()
            ->assertJsonPath('success', true);

        $campaign = Campaign::query()->findOrFail($campaignId);

        $this->assertSame(Campaign::STATUS_FINISHED, $campaign->status);
        $this->assertNotNull($campaign->finished_at);

        $this->assertDatabaseHas('campaign_contacts', [
            'campaign_id' => $campaignId,
            'phone' => '5511999991001',
            'status' => CampaignContact::STATUS_SUCCESS,
        ]);

        $this->assertDatabaseHas('campaign_contacts', [
            'campaign_id' => $campaignId,
            'phone' => '5511999991002',
            'status' => CampaignContact::STATUS_FAILED,
        ]);

        $this->assertDatabaseHas('campaign_messages', [
            'campaign_id' => $campaignId,
            'phone' => '5511999991001',
            'status' => CampaignMessage::STATUS_SUCCESS,
            'external_id' => 'campaign-msg-001',
        ]);

        $this->assertDatabaseHas('campaign_messages', [
            'campaign_id' => $campaignId,
            'phone' => '5511999991002',
            'status' => CampaignMessage::STATUS_FAILED,
        ]);

        $this->assertSame(2, CampaignMessage::query()->where('campaign_id', $campaignId)->count());

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://evolution.test/message/sendText/campaign_send_instance'
                && in_array($request['number'], ['5511999991001', '5511999991002'], true)
                && $request['textMessage']['text'] === 'Mensagem de campanha';
        });
    }

    public function test_it_can_pause_and_resume_a_scheduled_campaign(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'campaign-pause-company']);
        $sector = $this->createSector($company, ['slug' => 'campaign-pause-sector']);
        $instance = $this->createWhatsappInstance($company->id, $sector->id, 'campaign_pause_instance');
        $role = $this->createRole($company, 'manager', ['campaigns.view', 'campaigns.manage']);

        $this->attachUserToCompany($user, $company, $role);
        Sanctum::actingAs($user);

        $campaign = Campaign::query()->create([
            'company_id' => $company->id,
            'whatsapp_instance_id' => $instance->id,
            'name' => 'Campanha pausavel',
            'message' => 'Mensagem agendada',
            'send_limit_per_minute' => 20,
            'status' => Campaign::STATUS_DRAFT,
        ]);

        CampaignContact::query()->create([
            'company_id' => $company->id,
            'campaign_id' => $campaign->id,
            'name' => 'Cliente agendado',
            'phone' => '5511999992001',
            'status' => CampaignContact::STATUS_PENDING,
        ]);

        $futureDate = now()->addMinutes(10)->toAtomString();

        $scheduleResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/campaigns/%d/schedule', $campaign->id), [
                'scheduled_at' => $futureDate,
            ]);

        $scheduleResponse->assertOk()
            ->assertJsonPath('data.status', Campaign::STATUS_SCHEDULED);

        Queue::assertPushed(SendCampaignMessageJob::class);

        $pauseResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/campaigns/%d/pause', $campaign->id));

        $pauseResponse->assertOk()
            ->assertJsonPath('data.status', Campaign::STATUS_PAUSED);

        $resumeResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/campaigns/%d/resume', $campaign->id));

        $resumeResponse->assertOk()
            ->assertJsonPath('data.status', Campaign::STATUS_RUNNING);

        Queue::assertPushed(SendCampaignMessageJob::class, 2);
    }

    private function createWhatsappInstance(int $companyId, int $sectorId, string $instanceName): WhatsappInstance
    {
        return WhatsappInstance::query()->create([
            'company_id' => $companyId,
            'sector_id' => $sectorId,
            'instance_name' => $instanceName,
            'phone_number' => '5511888888888',
            'status' => 'connected',
            'metadata' => [],
        ]);
    }
}
