<?php

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\Deal;

class CreateDealAction
{
    public function execute(array $attributes): Deal
    {
        $deal = Deal::query()->create([
            'pipeline_id' => $attributes['pipeline_id'],
            'pipeline_stage_id' => $attributes['pipeline_stage_id'],
            'contact_id' => $attributes['contact_id'] ?? null,
            'assigned_user_id' => $attributes['assigned_user_id'],
            'title' => $attributes['title'],
            'value' => $attributes['value'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]);

        return $this->loadDeal($deal);
    }

    private function loadDeal(Deal $deal): Deal
    {
        return Deal::query()
            ->with(['pipeline', 'stage', 'contact', 'assignedUser'])
            ->findOrFail($deal->getKey());
    }
}
