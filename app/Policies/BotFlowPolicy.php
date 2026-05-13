<?php

namespace App\Policies;

use App\Domain\Chatbot\Models\BotFlow;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class BotFlowPolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission('chatbots.view')
            || $user->hasCompanyPermission('chatbots.manage');
    }

    public function view(User $user, BotFlow $botFlow): bool
    {
        return $this->hasCompanyAccess($user, $botFlow, 'chatbots.view')
            || $this->hasCompanyAccess($user, $botFlow, 'chatbots.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('chatbots.manage');
    }

    public function update(User $user, BotFlow $botFlow): bool
    {
        return $this->hasCompanyAccess($user, $botFlow, 'chatbots.manage');
    }

    public function delete(User $user, BotFlow $botFlow): bool
    {
        return $this->hasCompanyAccess($user, $botFlow, 'chatbots.manage');
    }
}
