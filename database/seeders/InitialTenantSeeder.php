<?php

namespace Database\Seeders;

use App\Domain\Tenants\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialTenantSeeder extends Seeder
{
    public function run()
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

        $tenant = Tenant::updateOrCreate(
            ['slug' => 'acme-support'],
            [
                'name' => 'Acme Support',
                'plan' => 'starter',
                'status' => 'active',
                'settings' => [
                    'locale' => 'pt-BR',
                ],
            ]
        );

        $tenant->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'owner',
                'is_active' => true,
            ],
        ]);

        $workspace = $tenant->workspaces()->updateOrCreate(
            ['slug' => 'main'],
            [
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
                'provider' => 'cloud_api',
                'status' => 'disconnected',
            ]
        );
    }
}
