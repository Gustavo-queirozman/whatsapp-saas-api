<?php

namespace Tests\Concerns;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\Permission;
use App\Domain\Companies\Models\Role;
use App\Domain\Companies\Models\Workspace;
use App\Domain\Conversations\Models\Contact;
use App\Domain\Queues\Models\Sector;
use App\Models\User;

trait CreatesCompanyContext
{
    protected function createCompany(array $attributes = []): Company
    {
        return Company::query()->create(array_merge([
            'name' => 'Empresa '.uniqid(),
            'slug' => 'empresa-'.uniqid(),
            'status' => 'active',
            'settings' => ['locale' => 'pt-BR'],
        ], $attributes));
    }

    protected function createRole(Company $company, string $slug = 'owner', array $permissionSlugs = ['companies.view']): Role
    {
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => ucfirst($slug),
            'slug' => $slug,
            'is_system' => true,
        ]);

        $permissions = collect($permissionSlugs)->map(function (string $permissionSlug): int {
            return Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                ['name' => $permissionSlug, 'description' => $permissionSlug]
            )->id;
        });

        $role->permissions()->sync($permissions->all());

        return $role;
    }

    protected function attachUserToCompany(
        User $user,
        Company $company,
        ?Role $role = null,
        bool $isActive = true
    ): void {
        $user->companyMemberships()->create([
            'company_id' => $company->id,
            'role_id' => $role?->id,
            'is_active' => $isActive,
        ]);
    }

    protected function createWorkspace(Company $company, array $attributes = []): Workspace
    {
        return Workspace::query()->create(array_merge([
            'company_id' => $company->id,
            'name' => 'Principal',
            'slug' => 'principal-'.uniqid(),
            'timezone' => 'America/Sao_Paulo',
            'settings' => [],
        ], $attributes));
    }

    protected function createSector(Company $company, array $attributes = []): Sector
    {
        return Sector::query()->create(array_merge([
            'company_id' => $company->id,
            'name' => 'Atendimento',
            'slug' => 'atendimento-'.uniqid(),
            'color' => '#2563eb',
            'settings' => [],
        ], $attributes));
    }

    protected function attachUserToSector(Sector $sector, User $user): void
    {
        $sector->users()->syncWithoutDetaching([
            $user->id => [
                'company_id' => $sector->company_id,
            ],
        ]);
    }

    protected function createContact(Company $company, Workspace $workspace, array $attributes = []): Contact
    {
        return Contact::query()->create(array_merge([
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'name' => 'Contato '.uniqid(),
            'phone' => '55119999'.random_int(1000, 9999),
            'metadata' => [],
        ], $attributes));
    }
}
