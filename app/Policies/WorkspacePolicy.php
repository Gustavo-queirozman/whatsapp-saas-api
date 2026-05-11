<?php

namespace App\Policies;

use App\Domain\Companies\Models\Workspace;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class WorkspacePolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission('workspaces.view');
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->hasCompanyAccess($user, $workspace, 'workspaces.view');
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('workspaces.manage');
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->hasCompanyAccess($user, $workspace, 'workspaces.manage');
    }
}
