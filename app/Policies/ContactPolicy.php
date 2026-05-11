<?php

namespace App\Policies;

use App\Domain\Conversations\Models\Contact;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class ContactPolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission('contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->hasCompanyAccess($user, $contact, 'contacts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission('contacts.manage');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->hasCompanyAccess($user, $contact, 'contacts.manage');
    }
}
