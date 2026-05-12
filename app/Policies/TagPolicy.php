<?php

namespace App\Policies;

use App\Domain\Tags\Models\Tag;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class TagPolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $this->hasTagPermission($user);
    }

    public function view(User $user, Tag $tag): bool
    {
        return $this->hasCompanyTagAccess($user, $tag);
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('tags.manage');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $this->hasCompanyAccess($user, $tag, 'tags.manage');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $this->hasCompanyAccess($user, $tag, 'tags.manage');
    }

    private function hasTagPermission(User $user): bool
    {
        return $user->hasCompanyPermission('tags.view')
            || $user->hasCompanyPermission('tags.manage');
    }

    private function hasCompanyTagAccess(User $user, Tag $tag): bool
    {
        return $this->hasCompanyAccess($user, $tag, 'tags.view')
            || $this->hasCompanyAccess($user, $tag, 'tags.manage');
    }
}
