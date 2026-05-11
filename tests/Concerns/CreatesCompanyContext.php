<?php

namespace Tests\Concerns;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\Permission;
use App\Domain\Companies\Models\Role;
use App\Domain\Companies\Models\Workspace;
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
}
