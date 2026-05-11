<?php

namespace App\Policies;

use App\Domain\Conversations\Models\Message;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class MessagePolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission('messages.view');
    }

    public function view(User $user, Message $message): bool
    {
        return $this->hasCompanyAccess($user, $message, 'messages.view');
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('messages.manage');
    }
}
