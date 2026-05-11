<?php

namespace App\Support;

use App\Domain\Companies\Models\Company;

class CurrentCompany
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->getKey();
    }

    public function hasCompany(): bool
    {
        return $this->company !== null;
    }
}
