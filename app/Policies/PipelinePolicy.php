<?php

namespace App\Policies;

use App\Domain\Crm\Models\Pipeline;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class PipelinePolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $this->hasCrmPermission($user);
    }

    public function view(User $user, Pipeline $pipeline): bool
    {
        return $this->hasCompanyAccess($user, $pipeline, 'crm.view')
            || $this->hasCompanyAccess($user, $pipeline, 'crm.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('crm.manage');
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return $this->hasCompanyAccess($user, $pipeline, 'crm.manage');
    }

    public function delete(User $user, Pipeline $pipeline): bool
    {
        return $this->hasCompanyAccess($user, $pipeline, 'crm.manage');
    }

    private function hasCrmPermission(User $user): bool
    {
        return $user->hasCompanyPermission('crm.view')
            || $user->hasCompanyPermission('crm.manage');
    }
}
