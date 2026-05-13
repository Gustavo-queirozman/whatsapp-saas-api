<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignContact;
use App\Services\Campaigns\CampaignDispatchService;
use Illuminate\Validation\ValidationException;

class ResumeCampaignAction
{
    public function __construct(
        private readonly CampaignDispatchService $dispatchService,
    ) {
    }

    public function execute(Campaign $campaign): Campaign
    {
        if ($campaign->status !== Campaign::STATUS_PAUSED) {
            throw ValidationException::withMessages([
                'campaign' => 'Somente campanhas pausadas podem ser retomadas.',
            ]);
        }

        $hasPendingContacts = $campaign->contacts()
            ->where('status', CampaignContact::STATUS_PENDING)
            ->exists();

        if (! $hasPendingContacts) {
            return $this->dispatchService->markFinished($campaign);
        }

        $hasActiveCampaign = Campaign::query()
            ->where('whatsapp_instance_id', $campaign->whatsapp_instance_id)
            ->whereKeyNot($campaign->getKey())
            ->whereIn('status', [Campaign::STATUS_SCHEDULED, Campaign::STATUS_RUNNING])
            ->exists();

        if ($hasActiveCampaign) {
            throw ValidationException::withMessages([
                'whatsapp_instance_id' => 'Ja existe outra campanha agendada ou em execucao para esta instancia.',
            ]);
        }

        $campaign->forceFill([
            'status' => Campaign::STATUS_RUNNING,
            'scheduled_at' => now(),
            'started_at' => $campaign->started_at ?? now(),
            'paused_at' => null,
        ])->save();

        $campaign = $this->dispatchService->refresh($campaign);
        $this->dispatchService->dispatchNext($campaign);

        return $this->dispatchService->refresh($campaign);
    }
}
