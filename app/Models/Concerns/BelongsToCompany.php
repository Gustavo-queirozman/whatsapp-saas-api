<?php

namespace App\Models\Concerns;

use App\Domain\Companies\Models\Company;
use App\Support\CurrentCompany;
use App\Support\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model): void {
            $currentCompanyId = app(CurrentCompany::class)->id();

            if ($currentCompanyId !== null && empty($model->company_id)) {
                $model->company_id = $currentCompanyId;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
