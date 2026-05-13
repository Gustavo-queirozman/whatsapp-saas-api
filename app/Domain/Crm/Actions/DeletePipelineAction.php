<?php

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\Pipeline;
use Illuminate\Validation\ValidationException;

class DeletePipelineAction
{
    public function execute(Pipeline $pipeline): void
    {
        if ($pipeline->stages()->exists() || $pipeline->deals()->exists()) {
            throw ValidationException::withMessages([
                'pipeline' => 'Remova os estagios e deals antes de excluir o pipeline.',
            ]);
        }

        $pipeline->delete();
    }
}
