<?php

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\PipelineStage;

class CreatePipelineStageAction
{
    public function execute(array $attributes): PipelineStage
    {
        $stage = PipelineStage::query()->create([
            'pipeline_id' => $attributes['pipeline_id'],
            'name' => $attributes['name'],
            'color' => $attributes['color'] ?? null,
            'position' => $attributes['position'] ?? 1,
        ]);

        return PipelineStage::query()
            ->with('pipeline')
            ->withCount('deals')
            ->findOrFail($stage->getKey());
    }
}
