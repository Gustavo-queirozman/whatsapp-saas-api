<?php

namespace App\Services\WhatsApp;

use App\Domain\Companies\Models\Workspace;
use RuntimeException;

class CompanyWorkspaceResolver
{
    public function resolveDefault(int $companyId): Workspace
    {
        $workspace = Workspace::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->first();

        if ($workspace === null) {
            throw new RuntimeException('Empresa da instancia nao possui workspace configurado.');
        }

        return $workspace;
    }
}
