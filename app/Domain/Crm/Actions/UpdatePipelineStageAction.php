<?php

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\PipelineStage;

class UpdatePipelineStageAction
{
    public function execute(PipelineStage $stage, array $attributes): PipelineStage
    {
        $stage->fill([
            'pipeline_id' => $attributes['pipeline_id'],
            'name' => $attributes['name'],
            'color' => $attributes['color'] ?? null,
            'position' => $attributes['position'] ?? 1,
        ])->save();

        return PipelineStage::query()
            ->with('pipeline')
            ->withCount('deals')
            ->findOrFail($stage->getKey());
    }
}
