<?php

namespace App\Support\Scopes;

use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $currentCompanyId = app(CurrentCompany::class)->id();

        if ($currentCompanyId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $currentCompanyId);
    }
}
