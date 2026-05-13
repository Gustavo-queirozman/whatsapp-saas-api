<?php

namespace App\Domain\Campaigns\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'whatsapp_instance_id' => $this->whatsapp_instance_id,
            'name' => $this->name,
            'message' => $this->message,
            'send_limit_per_minute' => $this->send_limit_per_minute,
            'status' => $this->status,
            'scheduled_at' => optional($this->scheduled_at)->toAtomString(),
            'started_at' => optional($this->started_at)->toAtomString(),
            'paused_at' => optional($this->paused_at)->toAtomString(),
            'finished_at' => optional($this->finished_at)->toAtomString(),
            'total_contacts' => (int) ($this->total_contacts ?? 0),
            'pending_contacts' => (int) ($this->pending_contacts ?? 0),
            'processing_contacts' => (int) ($this->processing_contacts ?? 0),
            'success_contacts' => (int) ($this->success_contacts ?? 0),
            'failed_contacts' => (int) ($this->failed_contacts ?? 0),
            'processed_contacts' => (int) (($this->success_contacts ?? 0) + ($this->failed_contacts ?? 0)),
            'whatsapp_instance' => $this->whenLoaded('whatsappInstance', function (): array {
                return [
                    'id' => $this->whatsappInstance?->id,
                    'instance_name' => $this->whatsappInstance?->instance_name,
                    'status' => $this->whatsappInstance?->status,
                    'phone_number' => $this->whatsappInstance?->phone_number,
                ];
            }),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
