<?php

namespace App\Policies;

use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class WhatsappInstancePolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission('whatsapp.view');
    }

    public function view(User $user, WhatsappInstance $whatsappInstance): bool
    {
        return $this->hasCompanyAccess($user, $whatsappInstance, 'whatsapp.view');
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('whatsapp.manage');
    }

    public function update(User $user, WhatsappInstance $whatsappInstance): bool
    {
        return $this->hasCompanyAccess($user, $whatsappInstance, 'whatsapp.manage');
    }
}
