<?php

namespace App\Policies;

use App\Domain\Queues\Models\Sector;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class SectorPolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $this->hasSectorPermission($user);
    }

    public function view(User $user, Sector $sector): bool
    {
        return $this->hasCompanyAccess($user, $sector, 'sectors.view')
            || $this->hasCompanyAccess($user, $sector, 'sectors.manage')
            || $this->hasCompanyAccess($user, $sector, 'conversations.view')
            || $this->hasCompanyAccess($user, $sector, 'conversations.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('sectors.manage');
    }

    public function update(User $user, Sector $sector): bool
    {
        return $this->hasCompanyAccess($user, $sector, 'sectors.manage');
    }

    public function delete(User $user, Sector $sector): bool
    {
        return $this->hasCompanyAccess($user, $sector, 'sectors.manage');
    }

    public function attachUser(User $user, Sector $sector): bool
    {
        return $this->hasCompanyAccess($user, $sector, 'sectors.manage');
    }

    public function detachUser(User $user, Sector $sector): bool
    {
        return $this->hasCompanyAccess($user, $sector, 'sectors.manage');
    }

    private function hasSectorPermission(User $user): bool
    {
        return $user->hasCompanyPermission('sectors.view')
            || $user->hasCompanyPermission('sectors.manage')
            || $user->hasCompanyPermission('conversations.view')
            || $user->hasCompanyPermission('conversations.manage');
    }
}
