<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignContact;
use App\Services\Campaigns\CampaignDispatchService;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ScheduleCampaignAction
{
    public function __construct(
        private readonly CampaignDispatchService $dispatchService,
    ) {
    }

    public function execute(Campaign $campaign, ?CarbonInterface $scheduledAt = null): Campaign
    {
        if ($campaign->status === Campaign::STATUS_FINISHED) {
            throw ValidationException::withMessages([
                'campaign' => 'Nao e permitido agendar uma campanha finalizada.',
            ]);
        }

        if ($campaign->status === Campaign::STATUS_RUNNING) {
            throw ValidationException::withMessages([
                'campaign' => 'A campanha ja esta em execucao.',
            ]);
        }

        $this->ensureCampaignCanStart($campaign);
        $this->ensureNoOtherActiveCampaignForInstance($campaign);

        $scheduledFor = $scheduledAt ?? now();
        $shouldRunNow = $scheduledFor->lessThanOrEqualTo(now());

        $campaign->forceFill([
            'status' => $shouldRunNow ? Campaign::STATUS_RUNNING : Campaign::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledFor,
            'started_at' => $shouldRunNow ? ($campaign->started_at ?? now()) : null,
            'paused_at' => null,
            'finished_at' => null,
        ])->save();

        $campaign = $this->dispatchService->refresh($campaign);
        $this->dispatchService->dispatchNext($campaign, $shouldRunNow ? null : $scheduledFor);

        return $this->dispatchService->refresh($campaign);
    }

    private function ensureCampaignCanStart(Campaign $campaign): void
    {
        $hasPendingContacts = $campaign->contacts()
            ->where('status', CampaignContact::STATUS_PENDING)
            ->exists();

        if (! $hasPendingContacts) {
            throw ValidationException::withMessages([
                'campaign' => 'A campanha precisa de ao menos um contato pendente para iniciar.',
            ]);
        }
    }

    private function ensureNoOtherActiveCampaignForInstance(Campaign $campaign): void
    {
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
    }
}
