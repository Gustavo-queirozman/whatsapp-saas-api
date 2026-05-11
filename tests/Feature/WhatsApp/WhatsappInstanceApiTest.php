<?php

namespace Tests\Feature\WhatsApp;

use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class WhatsappInstanceApiTest extends TestCase
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

    public function test_it_creates_a_whatsapp_instance_for_the_current_company_sector_and_calls_evolution(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-whatsapp']);
        $sector = $this->createSector($company, ['slug' => 'suporte']);
        $role = $this->createRole($company, 'owner', ['whatsapp.view', 'whatsapp.manage']);

        $this->attachUserToCompany($user, $company, $role);
        Sanctum::actingAs($user);

        Http::fake([
            'https://evolution.test/instance/create' => Http::response([
                'instance' => [
                    'instanceName' => 'acme_suporte',
                    'status' => 'connecting',
                ],
            ], 201),
        ]);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/whatsapp-instances', [
                'sector_id' => $sector->id,
                'instance_name' => 'acme_suporte',
                'phone_number' => '5511999999999',
                'metadata' => [
                    'label' => 'Canal principal',
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.sector_id', $sector->id)
            ->assertJsonPath('data.instance_name', 'acme_suporte')
            ->assertJsonPath('data.status', 'connecting')
            ->assertJsonPath('data.metadata.label', 'Canal principal');

        $this->assertDatabaseHas('whatsapp_instances', [
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'acme_suporte',
            'phone_number' => '5511999999999',
            'status' => 'connecting',
        ]);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://evolution.test/instance/create'
                && $request->hasHeader('apikey', 'test-api-key')
                && $request['instanceName'] === 'acme_suporte'
                && $request['number'] === '5511999999999';
        });
    }

    public function test_it_rejects_sector_ids_from_another_company_on_create(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-a']);
        $foreignCompany = $this->createCompany(['slug' => 'company-b']);
        $foreignSector = $this->createSector($foreignCompany, ['slug' => 'financeiro']);

        $this->attachUserToCompany($user, $company, $this->createRole($company, 'owner', ['whatsapp.manage']));
        Sanctum::actingAs($user);

        Http::fake();

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/whatsapp-instances', [
                'sector_id' => $foreignSector->id,
                'instance_name' => 'instancia_invalida',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sector_id']);

        $this->assertDatabaseCount('whatsapp_instances', 0);
        Http::assertNothingSent();
    }

    public function test_it_lists_and_shows_only_instances_from_the_current_company(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-visible']);
        $foreignCompany = $this->createCompany(['slug' => 'company-hidden']);
        $sector = $this->createSector($company, ['slug' => 'atendimento']);
        $foreignSector = $this->createSector($foreignCompany, ['slug' => 'comercial']);

        $this->attachUserToCompany($user, $company, $this->createRole($company, 'agent', ['whatsapp.view']));
        Sanctum::actingAs($user);

        $visibleInstance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'visivel',
            'phone_number' => '5511888888881',
            'status' => 'connected',
            'last_connection_at' => now(),
            'metadata' => [],
        ]);

        $foreignInstance = WhatsappInstance::query()->create([
            'company_id' => $foreignCompany->id,
            'sector_id' => $foreignSector->id,
            'instance_name' => 'oculta',
            'phone_number' => '5511888888882',
            'status' => 'disconnected',
            'metadata' => [],
        ]);

        $listResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/whatsapp-instances');

        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleInstance->id);

        $showResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/whatsapp-instances/%d', $visibleInstance->id));

        $showResponse->assertOk()
            ->assertJsonPath('data.id', $visibleInstance->id)
            ->assertJsonPath('data.sector.slug', 'atendimento');

        $hiddenResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/whatsapp-instances/%d', $foreignInstance->id));

        $hiddenResponse->assertNotFound();
    }

    public function test_it_fetches_the_qrcode_and_syncs_the_connection_status(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-qrcode']);
        $sector = $this->createSector($company, ['slug' => 'suporte']);

        $this->attachUserToCompany($user, $company, $this->createRole($company, 'owner', ['whatsapp.view']));
        Sanctum::actingAs($user);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'qrcode_instance',
            'phone_number' => '5511777777777',
            'status' => 'connecting',
            'metadata' => [],
        ]);

        Http::fake([
            'https://evolution.test/instance/connect/qrcode_instance*' => Http::response([
                'pairingCode' => 'ABC123',
                'code' => 'base64-qr-code',
            ], 200),
            'https://evolution.test/instance/connectionState/qrcode_instance' => Http::response([
                'instance' => [
                    'instanceName' => 'qrcode_instance',
                    'state' => 'open',
                ],
            ], 200),
        ]);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson(sprintf('/api/v1/whatsapp-instances/%d/qrcode', $instance->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.qrcode.pairingCode', 'ABC123')
            ->assertJsonPath('data.instance.status', 'connected');

        $instance->refresh();

        $this->assertSame('connected', $instance->status);
        $this->assertNotNull($instance->last_connection_at);
    }

    public function test_it_disconnects_and_deletes_an_instance(): void
    {
        $user = User::factory()->create();
        $company = $this->createCompany(['slug' => 'company-manage']);
        $sector = $this->createSector($company, ['slug' => 'operacao']);

        $this->attachUserToCompany($user, $company, $this->createRole($company, 'owner', ['whatsapp.manage']));
        Sanctum::actingAs($user);

        $instance = WhatsappInstance::query()->create([
            'company_id' => $company->id,
            'sector_id' => $sector->id,
            'instance_name' => 'delete_me',
            'phone_number' => '5511666666666',
            'status' => 'connected',
            'last_connection_at' => now(),
            'metadata' => [],
        ]);

        Http::fake([
            'https://evolution.test/instance/logout/delete_me' => Http::response([
                'status' => 'SUCCESS',
                'response' => [
                    'message' => 'Instance logged out',
                ],
            ], 200),
            'https://evolution.test/instance/delete/delete_me' => Http::response([
                'status' => 'SUCCESS',
                'response' => [
                    'message' => 'Instance deleted',
                ],
            ], 200),
        ]);

        $disconnectResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson(sprintf('/api/v1/whatsapp-instances/%d/disconnect', $instance->id));

        $disconnectResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'disconnected');

        $this->assertDatabaseHas('whatsapp_instances', [
            'id' => $instance->id,
            'status' => 'disconnected',
        ]);

        $deleteResponse = $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson(sprintf('/api/v1/whatsapp-instances/%d', $instance->id));

        $deleteResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Instancia removida com sucesso.');

        $this->assertDatabaseMissing('whatsapp_instances', [
            'id' => $instance->id,
        ]);
    }
}
