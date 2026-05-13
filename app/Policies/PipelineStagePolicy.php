<?php

namespace App\Policies;

use App\Domain\Crm\Models\PipelineStage;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class PipelineStagePolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $this->hasCrmPermission($user);
    }

    public function view(User $user, PipelineStage $pipelineStage): bool
    {
        return $this->hasCompanyAccess($user, $pipelineStage, 'crm.view')
            || $this->hasCompanyAccess($user, $pipelineStage, 'crm.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('crm.manage');
    }

    public function update(User $user, PipelineStage $pipelineStage): bool
    {
        return $this->hasCompanyAccess($user, $pipelineStage, 'crm.manage');
    }

    public function delete(User $user, PipelineStage $pipelineStage): bool
    {
        return $this->hasCompanyAccess($user, $pipelineStage, 'crm.manage');
    }

    private function hasCrmPermission(User $user): bool
    {
        return $user->hasCompanyPermission('crm.view')
            || $user->hasCompanyPermission('crm.manage');
    }
}
