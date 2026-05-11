<?php

namespace Tests\Feature\MultiTenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class SetCurrentCompanyTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    public function test_it_loads_the_first_active_company_when_the_header_is_not_provided(): void
    {
        $user = User::factory()->create();
        $firstCompany = $this->createCompany(['name' => 'Primeira', 'slug' => 'primeira']);
        $secondCompany = $this->createCompany(['name' => 'Segunda', 'slug' => 'segunda']);

        $role = $this->createRole($firstCompany, 'owner', ['companies.view']);
        $this->attachUserToCompany($user, $firstCompany, $role);
        $this->attachUserToCompany($user, $secondCompany, $this->createRole($secondCompany, 'owner', ['companies.view']));

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_company.id', $firstCompany->id);
    }

    public function test_it_loads_the_requested_company_when_the_user_belongs_to_it(): void
    {
        $user = User::factory()->create();
        $firstCompany = $this->createCompany(['slug' => 'company-a']);
        $secondCompany = $this->createCompany(['slug' => 'company-b']);

        $this->attachUserToCompany($user, $firstCompany, $this->createRole($firstCompany, 'owner', ['companies.view']));
        $this->attachUserToCompany($user, $secondCompany, $this->createRole($secondCompany, 'owner', ['companies.view']));

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Company-Id', (string) $secondCompany->id)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.current_company.id', $secondCompany->id);
    }

    public function test_it_blocks_access_when_the_requested_company_does_not_belong_to_the_user(): void
    {
        $user = User::factory()->create();
        $allowedCompany = $this->createCompany(['slug' => 'allowed']);
        $foreignCompany = $this->createCompany(['slug' => 'foreign']);

        $this->attachUserToCompany($user, $allowedCompany, $this->createRole($allowedCompany, 'owner', ['companies.view']));

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Company-Id', (string) $foreignCompany->id)
            ->getJson('/api/v1/auth/me');

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Acesso negado para a empresa informada.');
    }
}
