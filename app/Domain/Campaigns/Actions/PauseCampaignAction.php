<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Models\Campaign;
use App\Services\Campaigns\CampaignDispatchService;
use Illuminate\Validation\ValidationException;

class PauseCampaignAction
{
    public function __construct(
        private readonly CampaignDispatchService $dispatchService,
    ) {
    }

    public function execute(Campaign $campaign): Campaign
    {
        if (! in_array($campaign->status, [Campaign::STATUS_SCHEDULED, Campaign::STATUS_RUNNING], true)) {
            throw ValidationException::withMessages([
                'campaign' => 'Somente campanhas agendadas ou em execucao podem ser pausadas.',
            ]);
        }

        $campaign->forceFill([
            'status' => Campaign::STATUS_PAUSED,
            'paused_at' => now(),
        ])->save();

        return $this->dispatchService->refresh($campaign);
    }
}
