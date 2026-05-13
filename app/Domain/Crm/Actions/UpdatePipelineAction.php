<?php

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\Pipeline;

class UpdatePipelineAction
{
    public function execute(Pipeline $pipeline, array $attributes): Pipeline
    {
        $pipeline->fill([
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
        ])->save();

        return Pipeline::query()
            ->withCount(['stages', 'deals'])
            ->findOrFail($pipeline->getKey());
    }
}
