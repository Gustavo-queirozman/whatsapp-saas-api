<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignContact;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ImportCampaignContactsAction
{
    public function execute(Campaign $campaign, array $attributes): Collection
    {
        if ($campaign->status === Campaign::STATUS_FINISHED) {
            throw ValidationException::withMessages([
                'campaign' => 'Nao e permitido importar contatos em campanhas finalizadas.',
            ]);
        }

        $ids = [];

        foreach ($this->normalizeContacts($attributes['contacts']) as $contact) {
            $campaignContact = CampaignContact::query()->firstOrNew([
                'campaign_id' => $campaign->getKey(),
                'phone' => $contact['phone'],
            ]);

            $campaignContact->fill([
                'company_id' => $campaign->company_id,
                'name' => $contact['name'],
            ]);

            if (! $campaignContact->exists) {
                $campaignContact->status = CampaignContact::STATUS_PENDING;
            }

            $campaignContact->save();
            $ids[] = $campaignContact->getKey();
        }

        return CampaignContact::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();
    }

    private function normalizeContacts(array $contacts): Collection
    {
        return collect($contacts)
            ->values()
            ->map(function (array $contact, int $index): array {
                $phone = preg_replace('/\D+/', '', (string) ($contact['phone'] ?? ''));

                if (! is_string($phone) || strlen($phone) < 10 || strlen($phone) > 20) {
                    throw ValidationException::withMessages([
                        "contacts.$index.phone" => 'Informe um telefone valido com DDI/DDD.',
                    ]);
                }

                return [
                    'name' => isset($contact['name']) && trim((string) $contact['name']) !== ''
                        ? trim((string) $contact['name'])
                        : null,
                    'phone' => $phone,
                ];
            })
            ->keyBy('phone')
            ->values();
    }
}
