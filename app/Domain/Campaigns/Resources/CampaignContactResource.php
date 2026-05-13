<?php

namespace App\Domain\Campaigns\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CampaignContactResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'last_attempt_at' => optional($this->last_attempt_at)->toAtomString(),
            'sent_at' => optional($this->sent_at)->toAtomString(),
            'failed_at' => optional($this->failed_at)->toAtomString(),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
