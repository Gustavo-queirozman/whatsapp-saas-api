<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Validation\ValidationException;

class UpdateCampaignAction
{
    public function execute(Campaign $campaign, array $attributes): Campaign
    {
        if (in_array($campaign->status, [Campaign::STATUS_RUNNING, Campaign::STATUS_FINISHED], true)) {
            throw ValidationException::withMessages([
                'campaign' => 'Nao e permitido editar campanhas em execucao ou finalizadas.',
            ]);
        }

        $campaign->fill([
            'whatsapp_instance_id' => $attributes['whatsapp_instance_id'],
            'name' => $attributes['name'],
            'message' => $attributes['message'],
            'send_limit_per_minute' => $attributes['send_limit_per_minute'] ?? $campaign->send_limit_per_minute,
        ])->save();

        return Campaign::query()
            ->withSummary()
            ->findOrFail($campaign->getKey());
    }
}
