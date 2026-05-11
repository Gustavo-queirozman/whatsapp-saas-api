<?php

namespace App\Policies;

use App\Domain\Companies\Models\Company;
use App\Models\User;
use App\Policies\Concerns\ChecksCompanyAccess;

class CompanyPolicy
{
    use ChecksCompanyAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission('companies.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $this->hasCompanyAccess($user, $company, 'companies.view');
    }

    public function update(User $user, Company $company): bool
    {
        return $this->hasCompanyAccess($user, $company, 'companies.manage');
    }
}
