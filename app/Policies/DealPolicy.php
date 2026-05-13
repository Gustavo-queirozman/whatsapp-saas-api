<?php

namespace App\Policies;

use App\Domain\Crm\Models\Deal;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class DealPolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $this->hasCrmPermission($user);
    }

    public function view(User $user, Deal $deal): bool
    {
        return $this->hasCompanyAccess($user, $deal, 'crm.view')
            || $this->hasCompanyAccess($user, $deal, 'crm.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('crm.manage');
    }

    public function update(User $user, Deal $deal): bool
    {
        return $this->hasCompanyAccess($user, $deal, 'crm.manage');
    }

    public function delete(User $user, Deal $deal): bool
    {
        return $this->hasCompanyAccess($user, $deal, 'crm.manage');
    }

    public function moveStage(User $user, Deal $deal): bool
    {
        return $this->hasCompanyAccess($user, $deal, 'crm.manage');
    }

    private function hasCrmPermission(User $user): bool
    {
        return $user->hasCompanyPermission('crm.view')
            || $user->hasCompanyPermission('crm.manage');
    }
}
