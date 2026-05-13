<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Models\Campaign;

class CreateCampaignAction
{
    public function execute(array $attributes): Campaign
    {
        $campaign = Campaign::query()->create([
            'whatsapp_instance_id' => $attributes['whatsapp_instance_id'],
            'name' => $attributes['name'],
            'message' => $attributes['message'],
            'send_limit_per_minute' => $attributes['send_limit_per_minute'] ?? 20,
            'status' => Campaign::STATUS_DRAFT,
        ]);

        return Campaign::query()
            ->withSummary()
            ->findOrFail($campaign->getKey());
    }
}
