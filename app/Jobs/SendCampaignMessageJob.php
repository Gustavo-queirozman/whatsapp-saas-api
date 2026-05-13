<?php

namespace App\Jobs;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignContact;
use App\Domain\Campaigns\Models\CampaignMessage;
use App\Services\Campaigns\CampaignDispatchService;
use App\Services\EvolutionGateway\EvolutionClient;
use App\Services\EvolutionGateway\EvolutionMessageMetadataResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SendCampaignMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $campaignContactId,
    ) {
    }

    public function handle(
        EvolutionClient $evolutionClient,
        EvolutionMessageMetadataResolver $metadataResolver,
        CampaignDispatchService $dispatchService
    ): void {
        $campaign = Campaign::query()
            ->with('whatsappInstance')
            ->find($this->campaignId);

        if (! $campaign instanceof Campaign) {
            return;
        }

        $contact = CampaignContact::query()
            ->where('campaign_id', $campaign->getKey())
            ->find($this->campaignContactId);

        if (! $contact instanceof CampaignContact || $contact->status !== CampaignContact::STATUS_PENDING) {
            return;
        }

        if (in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_PAUSED, Campaign::STATUS_FINISHED], true)) {
            return;
        }

        if ($campaign->status === Campaign::STATUS_SCHEDULED && $campaign->scheduled_at?->isFuture()) {
            $this->release(max(1, now()->diffInSeconds($campaign->scheduled_at)));

            return;
        }

        if ($campaign->status === Campaign::STATUS_SCHEDULED) {
            $campaign->forceFill([
                'status' => Campaign::STATUS_RUNNING,
                'started_at' => $campaign->started_at ?? now(),
            ])->save();
        }

        $instance = $campaign->whatsappInstance;

        if ($instance === null) {
            if ($this->claimContact($contact)) {
                $this->registerFailure($campaign, $contact, 'A campanha nao possui uma instancia valida para envio.');
            }

            $this->continueCampaign($campaign, $dispatchService);

            return;
        }

        $lock = Cache::lock(sprintf('campaign-instance-lock:%d', $instance->getKey()), 30);

        if (! $lock->get()) {
            $this->release(5);

            return;
        }

        try {
            $limitKey = sprintf('campaign-instance-rate:%d', $instance->getKey());
            $limit = max(1, (int) $campaign->send_limit_per_minute);

            if (RateLimiter::tooManyAttempts($limitKey, $limit)) {
                $this->release(max(1, RateLimiter::availableIn($limitKey)));

                return;
            }

            if (! $this->claimContact($contact)) {
                return;
            }

            RateLimiter::hit($limitKey, 60);

            try {
                $response = $evolutionClient->sendTextMessage(
                    $instance->instance_name,
                    $contact->phone,
                    $campaign->message,
                );

                $this->registerSuccess($campaign, $contact, $response, $metadataResolver);
            } catch (Throwable $exception) {
                $this->registerFailure($campaign, $contact, $exception->getMessage());
            }
        } finally {
            $lock->release();
        }

        $this->continueCampaign($campaign, $dispatchService);
    }

    private function claimContact(CampaignContact $contact): bool
    {
        $updated = CampaignContact::query()
            ->whereKey($contact->getKey())
            ->where('status', CampaignContact::STATUS_PENDING)
            ->update([
                'status' => CampaignContact::STATUS_PROCESSING,
                'last_attempt_at' => now(),
                'error_message' => null,
            ]);

        return $updated === 1;
    }

    private function registerSuccess(
        Campaign $campaign,
        CampaignContact $contact,
        array $response,
        EvolutionMessageMetadataResolver $metadataResolver
    ): void {
        DB::transaction(function () use ($campaign, $contact, $response, $metadataResolver): void {
            CampaignMessage::query()->create([
                'company_id' => $campaign->company_id,
                'campaign_id' => $campaign->getKey(),
                'campaign_contact_id' => $contact->getKey(),
                'whatsapp_instance_id' => $campaign->whatsapp_instance_id,
                'status' => CampaignMessage::STATUS_SUCCESS,
                'phone' => $contact->phone,
                'external_id' => $metadataResolver->extractExternalId($response),
                'body' => $campaign->message,
                'response_payload' => $response,
                'sent_at' => $metadataResolver->resolveSentAt($response)?->toDateTimeString()
                    ?? now()->toDateTimeString(),
            ]);

            $contact->forceFill([
                'status' => CampaignContact::STATUS_SUCCESS,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();
        });
    }

    private function registerFailure(Campaign $campaign, CampaignContact $contact, string $errorMessage): void
    {
        DB::transaction(function () use ($campaign, $contact, $errorMessage): void {
            CampaignMessage::query()->create([
                'company_id' => $campaign->company_id,
                'campaign_id' => $campaign->getKey(),
                'campaign_contact_id' => $contact->getKey(),
                'whatsapp_instance_id' => $campaign->whatsapp_instance_id,
                'status' => CampaignMessage::STATUS_FAILED,
                'phone' => $contact->phone,
                'body' => $campaign->message,
                'error_message' => $errorMessage,
                'failed_at' => now()->toDateTimeString(),
            ]);

            $contact->forceFill([
                'status' => CampaignContact::STATUS_FAILED,
                'failed_at' => now(),
                'sent_at' => null,
                'error_message' => $errorMessage,
            ])->save();
        });
    }

    private function continueCampaign(Campaign $campaign, CampaignDispatchService $dispatchService): void
    {
        $freshCampaign = Campaign::query()->find($campaign->getKey());

        if (! $freshCampaign instanceof Campaign) {
            return;
        }

        if ($freshCampaign->status === Campaign::STATUS_PAUSED) {
            return;
        }

        $dispatchService->dispatchNext($freshCampaign);
    }
}
