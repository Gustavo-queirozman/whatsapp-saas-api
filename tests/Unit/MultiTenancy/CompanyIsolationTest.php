<?php

namespace Tests\Unit\MultiTenancy;

use App\Domain\Conversations\Models\Contact;
use App\Models\User;
use App\Policies\ContactPolicy;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCompanyContext;
use Tests\TestCase;

class CompanyIsolationTest extends TestCase
{
    use CreatesCompanyContext;
    use RefreshDatabase;

    public function test_it_applies_the_company_scope_to_company_owned_models(): void
    {
        $firstCompany = $this->createCompany(['slug' => 'scope-a']);
        $secondCompany = $this->createCompany(['slug' => 'scope-b']);
        $firstWorkspace = $this->createWorkspace($firstCompany, ['slug' => 'workspace-a']);
        $secondWorkspace = $this->createWorkspace($secondCompany, ['slug' => 'workspace-b']);

        $firstContact = Contact::query()->create([
            'company_id' => $firstCompany->id,
            'workspace_id' => $firstWorkspace->id,
            'name' => 'Contato A',
            'phone' => '5511999999991',
        ]);

        Contact::query()->create([
            'company_id' => $secondCompany->id,
            'workspace_id' => $secondWorkspace->id,
            'name' => 'Contato B',
            'phone' => '5511999999992',
        ]);

        app(CurrentCompany::class)->set($firstCompany);

        $visibleContacts = Contact::query()->pluck('id');

        $this->assertCount(1, $visibleContacts);
        $this->assertTrue($visibleContacts->contains($firstContact->id));
    }

    public function test_contact_policy_denies_access_to_another_company_data(): void
    {
        $user = User::factory()->create();
        $allowedCompany = $this->createCompany(['slug' => 'policy-a']);
        $foreignCompany = $this->createCompany(['slug' => 'policy-b']);
        $allowedWorkspace = $this->createWorkspace($allowedCompany, ['slug' => 'workspace-policy-a']);
        $foreignWorkspace = $this->createWorkspace($foreignCompany, ['slug' => 'workspace-policy-b']);
        $role = $this->createRole($allowedCompany, 'agent', ['contacts.view']);

        $this->attachUserToCompany($user, $allowedCompany, $role);

        $allowedContact = Contact::query()->create([
            'company_id' => $allowedCompany->id,
            'workspace_id' => $allowedWorkspace->id,
            'name' => 'Permitido',
            'phone' => '5511888888881',
        ]);

        $foreignContact = Contact::query()->create([
            'company_id' => $foreignCompany->id,
            'workspace_id' => $foreignWorkspace->id,
            'name' => 'Bloqueado',
            'phone' => '5511888888882',
        ]);

        $policy = new ContactPolicy();

        $this->assertTrue($policy->view($user, $allowedContact));
        $this->assertFalse($policy->view($user, $foreignContact));
    }
}
