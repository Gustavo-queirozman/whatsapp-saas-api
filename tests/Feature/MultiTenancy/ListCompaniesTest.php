<?php

namespace Tests\Feature\MultiTenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class ListCompaniesTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    public function test_it_lists_only_active_companies_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $activeCompany = $this->createCompany(['slug' => 'active-company']);
        $inactiveCompany = $this->createCompany(['slug' => 'inactive-company']);
        $foreignCompany = $this->createCompany(['slug' => 'foreign-company']);

        $this->attachUserToCompany($user, $activeCompany, $this->createRole($activeCompany, 'owner', ['companies.view']), true);
        $this->attachUserToCompany($user, $inactiveCompany, $this->createRole($inactiveCompany, 'owner', ['companies.view']), false);
        $this->attachUserToCompany(User::factory()->create(), $foreignCompany, $this->createRole($foreignCompany, 'owner', ['companies.view']), true);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/companies');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeCompany->id)
            ->assertJsonPath('data.0.is_current', true);
    }
}
