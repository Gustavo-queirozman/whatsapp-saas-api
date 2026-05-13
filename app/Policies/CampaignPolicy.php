<?php

namespace App\Policies;

use App\Domain\Campaigns\Models\Campaign;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class CampaignPolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $this->hasCampaignPermission($user);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $this->hasCompanyAccess($user, $campaign, 'campaigns.view')
            || $this->hasCompanyAccess($user, $campaign, 'campaigns.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('campaigns.manage');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->hasCompanyAccess($user, $campaign, 'campaigns.manage');
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->hasCompanyAccess($user, $campaign, 'campaigns.manage');
    }

    private function hasCampaignPermission(User $user): bool
    {
        return $user->hasCompanyPermission('campaigns.view')
            || $user->hasCompanyPermission('campaigns.manage');
    }
}
