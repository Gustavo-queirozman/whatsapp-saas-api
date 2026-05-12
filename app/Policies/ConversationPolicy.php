<?php

namespace App\Policies;

use App\Domain\Conversations\Models\Conversation;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class ConversationPolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $this->hasConversationPermission($user);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->hasCompanyConversationAccess($user, $conversation);
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('conversations.manage');
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->hasCompanyAccess($user, $conversation, 'conversations.manage');
    }

    public function assignToSelf(User $user, Conversation $conversation): bool
    {
        return $this->hasCompanyConversationAccess($user, $conversation);
    }

    public function assignUser(User $user, Conversation $conversation): bool
    {
        return $this->hasCompanyAccess($user, $conversation, 'conversations.manage');
    }

    public function transferSector(User $user, Conversation $conversation): bool
    {
        return $this->hasCompanyAccess($user, $conversation, 'conversations.manage');
    }

    private function hasConversationPermission(User $user): bool
    {
        return $user->hasCompanyPermission('conversations.view')
            || $user->hasCompanyPermission('conversations.manage');
    }

    private function hasCompanyConversationAccess(User $user, Conversation $conversation): bool
    {
        return $this->hasCompanyAccess($user, $conversation, 'conversations.view')
            || $this->hasCompanyAccess($user, $conversation, 'conversations.manage');
    }
}
