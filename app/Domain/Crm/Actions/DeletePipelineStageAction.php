<?php

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\PipelineStage;
use Illuminate\Validation\ValidationException;

class DeletePipelineStageAction
{
    public function execute(PipelineStage $stage): void
    {
        if ($stage->deals()->exists()) {
            throw ValidationException::withMessages([
                'pipeline_stage' => 'Remova ou mova os deals antes de excluir o estagio.',
            ]);
        }

        $stage->delete();
    }
}
