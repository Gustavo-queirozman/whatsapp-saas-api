<?php

namespace Database\Seeders;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\Permission;
use App\Domain\Companies\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialCompanySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@local.test'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $company = Company::updateOrCreate(
            ['slug' => 'acme-support'],
            [
                'name' => 'Acme Support',
                'status' => 'active',
                'settings' => [
                    'locale' => 'pt-BR',
                ],
            ]
        );

        $permissions = collect([
            ['name' => 'Visualizar empresas', 'slug' => 'companies.view'],
            ['name' => 'Gerenciar empresas', 'slug' => 'companies.manage'],
            ['name' => 'Visualizar contatos', 'slug' => 'contacts.view'],
            ['name' => 'Gerenciar contatos', 'slug' => 'contacts.manage'],
            ['name' => 'Visualizar conversas', 'slug' => 'conversations.view'],
            ['name' => 'Gerenciar conversas', 'slug' => 'conversations.manage'],
            ['name' => 'Visualizar WhatsApp', 'slug' => 'whatsapp.view'],
            ['name' => 'Gerenciar WhatsApp', 'slug' => 'whatsapp.manage'],
            ['name' => 'Visualizar workspaces', 'slug' => 'workspaces.view'],
            ['name' => 'Gerenciar workspaces', 'slug' => 'workspaces.manage'],
            ['name' => 'Visualizar mensagens', 'slug' => 'messages.view'],
            ['name' => 'Gerenciar mensagens', 'slug' => 'messages.manage'],
        ])->map(fn (array $attributes): Permission => Permission::updateOrCreate(
            ['slug' => $attributes['slug']],
            $attributes
        ));

        $ownerRole = Role::updateOrCreate(
            [
                'company_id' => $company->id,
                'slug' => 'owner',
            ],
            [
                'name' => 'Owner',
                'description' => 'Acesso total a empresa.',
                'is_system' => true,
            ]
        );

        $ownerRole->permissions()->sync($permissions->pluck('id')->all());

        $company->companyUsers()->updateOrCreate([
            'user_id' => $user->id,
        ], [
            'role_id' => $ownerRole->id,
            'is_active' => true,
        ]);

        $workspace = $company->workspaces()->updateOrCreate(
            ['slug' => 'main'],
            [
                'company_id' => $company->id,
                'name' => 'Operacao Principal',
                'timezone' => 'America/Sao_Paulo',
                'settings' => [
                    'inbox_mode' => 'shared',
                ],
            ]
        );

        $workspace->whatsappInstances()->updateOrCreate(
            ['name' => 'Canal Principal'],
            [
                'company_id' => $company->id,
                'provider' => 'cloud_api',
                'status' => 'disconnected',
            ]
        );
    }
}
