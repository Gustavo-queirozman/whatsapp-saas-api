<?php

namespace App\Policies\Concerns;

use App\Domain\Companies\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ChecksCompanyAccess
{
    protected function hasCompanyAccess(User $user, Company|Model $resource, string $permission): bool
    {
        $company = $resource instanceof Company ? $resource : $resource->company;

        if (! $company instanceof Company) {
            return false;
        }

        if (! $user->belongsToCompany($company)) {
            return false;
        }

        return $user->hasCompanyPermission($permission, $company);
    }
}
