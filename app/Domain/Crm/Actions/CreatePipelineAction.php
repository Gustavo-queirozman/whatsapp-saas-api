<?php

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\Pipeline;

class CreatePipelineAction
{
    public function execute(array $attributes): Pipeline
    {
        $pipeline = Pipeline::query()->create([
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
        ]);

        return Pipeline::query()
            ->withCount(['stages', 'deals'])
            ->findOrFail($pipeline->getKey());
    }
}
