<?php

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\Deal;
use App\Domain\Crm\Models\PipelineStage;

class MoveDealStageAction
{
    public function execute(Deal $deal, PipelineStage $stage): Deal
    {
        $deal->fill([
            'pipeline_id' => $stage->pipeline_id,
            'pipeline_stage_id' => $stage->getKey(),
        ])->save();

        return Deal::query()
            ->with(['pipeline', 'stage', 'contact', 'assignedUser'])
            ->findOrFail($deal->getKey());
    }
}
