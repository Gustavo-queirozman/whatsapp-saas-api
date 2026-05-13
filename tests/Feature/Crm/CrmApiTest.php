<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\Models\Deal;
use App\Domain\Crm\Models\Pipeline;
use App\Domain\Crm\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class CrmApiTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    public function test_it_can_crud_pipelines_stages_and_deals_with_company_isolation(): void
    {
        $manager = User::factory()->create();
        $responsibleUser = User::factory()->create();
        $foreignResponsibleUser = User::factory()->create();

        $company = $this->createCompany(['slug' => 'crm-company']);
        $foreignCompany = $this->createCompany(['slug' => 'crm-company-foreign']);
        $workspace = $this->createWorkspace($company, ['slug' => 'crm-workspace']);
        $foreignWorkspace = $this->createWorkspace($foreignCompany, ['slug' => 'crm-workspace-foreign']);

        $managerRole = $this->createRole($company, 'crm-manager', ['crm.view', 'crm.manage']);
        $responsibleRole = $this->createRole($company, 'crm-agent', ['crm.view']);
        $foreignRole = $this->createRole($foreignCompany, 'crm-manager', ['crm.view', 'crm.manage']);

        $this->attachUserToCompany($manager, $company, $managerRole);
        $this->attachUserToCompany($manager, $foreignCompany, $foreignRole);
        $this->attachUserToCompany($responsibleUser, $company, $responsibleRole);
        $this->attachUserToCompany($foreignResponsibleUser, $foreignCompany, $foreignRole);

        Sanctum::actingAs($manager);

        $visiblePipeline = Pipeline::query()->create([
            'company_id' => $company->id,
            'name' => 'Comercial',
            'description' => 'Pipeline principal',
        ]);

        $visibleStage = PipelineStage::query()->create([
            'company_id' => $company->id,
            'pipeline_id' => $visiblePipeline->id,
            'name' => 'Entrada',
            'color' => '#2563EB',
            'position' => 1,
        ]);

        $secondaryPipeline = Pipeline::query()->create([
            'company_id' => $company->id,
            'name' => 'Upsell',
            'description' => 'Pipeline secundario',
        ]);

        $secondaryStage = PipelineStage::query()->create([
            'company_id' => $company->id,
            'pipeline_id' => $secondaryPipeline->id,
            'name' => 'Qualificado',
            'color' => '#16A34A',
            'position' => 1,
        ]);

        $hiddenPipeline = Pipeline::query()->create([
            'company_id' => $foreignCompany->id,
            'name' => 'Oculto',
            'description' => 'Pipeline externo',
        ]);

        $hiddenStage = PipelineStage::query()->create([
            'company_id' => $foreignCompany->id,
            'pipeline_id' => $hiddenPipeline->id,
            'name' => 'Oculto',
            'color' => '#DC2626',
            'position' => 1,
        ]);

        $contact = $this->createContact($company, $workspace, [
            'name' => 'Cliente CRM',
            'phone' => '5511999997001',
        ]);

        $foreignContact = $this->createContact($foreignCompany, $foreignWorkspace, [
            'name' => 'Cliente Oculto',
            'phone' => '5511999997002',
        ]);

        $visibleDeal = Deal::query()->create([
            'company_id' => $company->id,
            'pipeline_id' => $visiblePipeline->id,
            'pipeline_stage_id' => $visibleStage->id,
            'contact_id' => $contact->id,
            'assigned_user_id' => $responsibleUser->id,
            'title' => 'Proposta inicial',
            'value' => 1500.00,
            'notes' => 'Contato quente',
        ]);

        $hiddenDeal = Deal::query()->create([
            'company_id' => $foreignCompany->id,
            'pipeline_id' => $hiddenPipeline->id,
            'pipeline_stage_id' => $hiddenStage->id,
            'contact_id' => $foreignContact->id,
            'assigned_user_id' => $foreignResponsibleUser->id,
            'title' => 'Deal oculto',
            'value' => 900.00,
            'notes' => 'Nao deve aparecer',
        ]);

        $listPipelinesResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/pipelines');

        $listPipelinesResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['id' => $hiddenPipeline->id]);

        $storePipelineResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/pipelines', [
                'company_id' => $foreignCompany->id,
                'name' => 'Parcerias',
                'description' => 'Canal indireto',
            ]);

        $storePipelineResponse->assertCreated()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.name', 'Parcerias');

        $createdPipelineId = (int) $storePipelineResponse->json('data.id');

        $invalidStageResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/pipeline-stages', [
                'pipeline_id' => $hiddenPipeline->id,
                'name' => 'Invalido',
                'color' => '#F97316',
                'position' => 2,
            ]);

        $invalidStageResponse->assertStatus(422)
            ->assertJsonValidationErrors(['pipeline_id']);

        $storeStageResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/pipeline-stages', [
                'pipeline_id' => $createdPipelineId,
                'name' => 'Novo estagio',
                'color' => '#F97316',
                'position' => 2,
            ]);

        $storeStageResponse->assertCreated()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.pipeline_id', $createdPipelineId)
            ->assertJsonPath('data.name', 'Novo estagio');

        $createdStageId = (int) $storeStageResponse->json('data.id');

        $showStageResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/pipeline-stages/%d', $createdStageId));

        $showStageResponse->assertOk()
            ->assertJsonPath('data.pipeline.id', $createdPipelineId);

        $hiddenStageResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/pipeline-stages/%d', $hiddenStage->id));

        $hiddenStageResponse->assertNotFound();

        $invalidDealResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/deals', [
                'pipeline_id' => $visiblePipeline->id,
                'pipeline_stage_id' => $hiddenStage->id,
                'contact_id' => $foreignContact->id,
                'assigned_user_id' => $foreignResponsibleUser->id,
                'title' => 'Deal invalido',
                'value' => 300,
                'notes' => 'Nao pode aceitar dados externos',
            ]);

        $invalidDealResponse->assertStatus(422)
            ->assertJsonValidationErrors(['pipeline_stage_id', 'contact_id', 'assigned_user_id']);

        $storeDealResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/deals', [
                'company_id' => $foreignCompany->id,
                'pipeline_id' => $visiblePipeline->id,
                'pipeline_stage_id' => $visibleStage->id,
                'contact_id' => $contact->id,
                'assigned_user_id' => $responsibleUser->id,
                'title' => 'Cliente CRM - fechamento',
                'value' => 2450.75,
                'notes' => 'Originado do contato CRM',
            ]);

        $storeDealResponse->assertCreated()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.contact.id', $contact->id)
            ->assertJsonPath('data.assigned_user.id', $responsibleUser->id)
            ->assertJsonPath('data.stage.id', $visibleStage->id)
            ->assertJsonPath('data.value', '2450.75');

        $createdDealId = (int) $storeDealResponse->json('data.id');

        $this->assertDatabaseHas('deals', [
            'id' => $createdDealId,
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $visiblePipeline->id,
            'pipeline_stage_id' => $visibleStage->id,
        ]);

        $listDealsResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/deals?search=Cliente CRM');

        $listDealsResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['title' => 'Deal oculto']);

        $showDealResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/deals/%d', $createdDealId));

        $showDealResponse->assertOk()
            ->assertJsonPath('data.id', $createdDealId)
            ->assertJsonPath('data.contact.name', 'Cliente CRM');

        $hiddenDealResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/deals/%d', $hiddenDeal->id));

        $hiddenDealResponse->assertNotFound();

        $updateDealResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson(sprintf('/api/v1/deals/%d', $createdDealId), [
                'pipeline_id' => $visiblePipeline->id,
                'pipeline_stage_id' => $visibleStage->id,
                'contact_id' => $contact->id,
                'assigned_user_id' => $responsibleUser->id,
                'title' => 'Cliente CRM - proposta ajustada',
                'value' => 3100,
                'notes' => 'Negociacao avancou',
            ]);

        $updateDealResponse->assertOk()
            ->assertJsonPath('data.title', 'Cliente CRM - proposta ajustada')
            ->assertJsonPath('data.value', '3100.00')
            ->assertJsonPath('data.notes', 'Negociacao avancou');

        $moveStageResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/deals/%d/move-stage', $createdDealId), [
                'pipeline_stage_id' => $secondaryStage->id,
            ]);

        $moveStageResponse->assertOk()
            ->assertJsonPath('data.pipeline_id', $secondaryPipeline->id)
            ->assertJsonPath('data.pipeline_stage_id', $secondaryStage->id)
            ->assertJsonPath('data.stage.name', 'Qualificado');

        $this->assertDatabaseHas('deals', [
            'id' => $createdDealId,
            'pipeline_id' => $secondaryPipeline->id,
            'pipeline_stage_id' => $secondaryStage->id,
        ]);

        $blockedStageDeleteResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/pipeline-stages/%d', $secondaryStage->id));

        $blockedStageDeleteResponse->assertStatus(422)
            ->assertJsonValidationErrors(['pipeline_stage']);

        $blockedPipelineDeleteResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/pipelines/%d', $secondaryPipeline->id));

        $blockedPipelineDeleteResponse->assertStatus(422)
            ->assertJsonValidationErrors(['pipeline']);

        $deleteDealResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/deals/%d', $createdDealId));

        $deleteDealResponse->assertOk()
            ->assertJsonPath('data.message', 'Deal removido com sucesso.');

        $deleteStageResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/pipeline-stages/%d', $createdStageId));

        $deleteStageResponse->assertOk()
            ->assertJsonPath('data.message', 'Estagio removido com sucesso.');

        $deletePipelineResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/pipelines/%d', $createdPipelineId));

        $deletePipelineResponse->assertOk()
            ->assertJsonPath('data.message', 'Pipeline removido com sucesso.');

        $this->assertDatabaseMissing('deals', [
            'id' => $createdDealId,
        ]);

        $this->assertDatabaseMissing('pipeline_stages', [
            'id' => $createdStageId,
        ]);

        $this->assertDatabaseMissing('pipelines', [
            'id' => $createdPipelineId,
        ]);
    }
}
