<?php

namespace Tests\Feature\Chatbot;

use App\Domain\Chatbot\Models\BotFlow;
use App\Domain\Queues\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class BotFlowApiTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    public function test_it_can_crud_bot_flows_with_nested_options_and_company_isolation(): void
    {
        $manager = User::factory()->create();
        $foreignManager = User::factory()->create();

        $company = $this->createCompany(['slug' => 'company-chatbot']);
        $foreignCompany = $this->createCompany(['slug' => 'company-chatbot-foreign']);

        $managerRole = $this->createRole($company, 'chatbot-manager', ['chatbots.view', 'chatbots.manage']);
        $foreignRole = $this->createRole($foreignCompany, 'chatbot-manager', ['chatbots.view', 'chatbots.manage']);

        $this->attachUserToCompany($manager, $company, $managerRole);
        $this->attachUserToCompany($foreignManager, $foreignCompany, $foreignRole);

        $support = $this->createSector($company, [
            'name' => 'Suporte Bot',
            'slug' => 'suporte-bot',
        ]);

        $billing = $this->createSector($company, [
            'name' => 'Financeiro Bot',
            'slug' => 'financeiro-bot',
        ]);

        $foreignSector = $this->createSector($foreignCompany, [
            'name' => 'Oculto Bot',
            'slug' => 'oculto-bot',
        ]);

        Sanctum::actingAs($manager);

        $existingFlow = BotFlow::query()->create([
            'company_id' => $company->id,
            'sector_id' => $support->id,
            'name' => 'Fluxo legado',
            'is_active' => true,
            'welcome_message' => 'Fluxo antigo',
        ]);

        $storeResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/bot-flows', [
                'company_id' => $foreignCompany->id,
                'sector_id' => $support->id,
                'name' => 'Fluxo principal',
                'is_active' => true,
                'welcome_message' => 'Ola! Escolha uma opcao.',
                'invalid_option_message' => 'Opcao invalida.',
                'options' => [
                    [
                        'label' => 'Atendimento',
                        'number' => '1',
                        'keywords' => ['atendimento', 'suporte'],
                        'action' => 'reply',
                        'response_message' => 'Vamos continuar por aqui.',
                    ],
                    [
                        'label' => 'Financeiro',
                        'number' => '2',
                        'keywords' => ['financeiro'],
                        'action' => 'transfer_sector',
                        'target_sector_id' => $billing->id,
                        'response_message' => 'Encaminhando para o financeiro.',
                    ],
                ],
            ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.sector_id', $support->id)
            ->assertJsonPath('data.name', 'Fluxo principal')
            ->assertJsonPath('data.options.1.target_sector_id', $billing->id);

        $createdFlowId = (int) $storeResponse->json('data.id');

        $this->assertDatabaseHas('bot_flows', [
            'id' => $createdFlowId,
            'company_id' => $company->id,
            'sector_id' => $support->id,
            'name' => 'Fluxo principal',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('bot_flows', [
            'id' => $existingFlow->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('bot_flow_options', [
            'company_id' => $company->id,
            'bot_flow_id' => $createdFlowId,
            'label' => 'Financeiro',
            'action' => 'transfer_sector',
            'target_sector_id' => $billing->id,
        ]);

        $listResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/bot-flows');

        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['sector_id' => $foreignSector->id]);

        $showResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/bot-flows/%d', $createdFlowId));

        $showResponse->assertOk()
            ->assertJsonPath('data.id', $createdFlowId)
            ->assertJsonPath('data.options.0.number', '1');

        $updateResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson(sprintf('/api/v1/bot-flows/%d', $createdFlowId), [
                'sector_id' => $billing->id,
                'name' => 'Fluxo financeiro',
                'is_active' => false,
                'welcome_message' => 'Bem-vindo ao financeiro.',
                'menu_message' => '1. Boleto',
                'invalid_option_message' => 'Informe 1.',
                'options' => [
                    [
                        'label' => 'Boleto',
                        'number' => '1',
                        'keywords' => ['boleto'],
                        'action' => 'open_queue',
                        'response_message' => 'Sua solicitacao foi enviada para a fila.',
                    ],
                ],
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sector_id', $billing->id)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.options.0.action', 'open_queue');

        $hiddenResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/bot-flows/%d', $this->createForeignFlow($foreignCompany, $foreignSector)->id));

        $hiddenResponse->assertNotFound();

        $destroyResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/bot-flows/%d', $createdFlowId));

        $destroyResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('bot_flows', [
            'id' => $createdFlowId,
        ]);
    }

    private function createForeignFlow(\App\Domain\Companies\Models\Company $company, Sector $sector): BotFlow
    {
        return BotFlow::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'name' => 'Fluxo oculto',
            'is_active' => true,
        ]);
    }
}
