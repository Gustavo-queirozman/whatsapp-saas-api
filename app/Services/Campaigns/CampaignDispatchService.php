<?php

namespace App\Services\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignContact;
use App\Jobs\SendCampaignMessageJob;
use Carbon\CarbonInterface;

class CampaignDispatchService
{
    public function dispatchNext(Campaign $campaign, ?CarbonInterface $delayUntil = null): void
    {
        if (! in_array($campaign->status, [Campaign::STATUS_SCHEDULED, Campaign::STATUS_RUNNING], true)) {
            return;
        }

        $nextContactId = $campaign->contacts()
            ->where('status', CampaignContact::STATUS_PENDING)
            ->orderBy('id')
            ->value('id');

        if ($nextContactId === null) {
            $this->markFinished($campaign);

            return;
        }

        $job = new SendCampaignMessageJob($campaign->getKey(), $nextContactId);

        if ($delayUntil !== null && $delayUntil->isFuture()) {
            $job->delay($delayUntil);
        }

        dispatch($job);
    }

    public function markFinished(Campaign $campaign): Campaign
    {
        $campaign->forceFill([
            'status' => Campaign::STATUS_FINISHED,
            'finished_at' => $campaign->finished_at ?? now(),
            'paused_at' => null,
        ])->save();

        return $this->refresh($campaign);
    }

    public function refresh(Campaign $campaign): Campaign
    {
        return Campaign::query()
            ->withSummary()
            ->findOrFail($campaign->getKey());
    }
}
