<?php

namespace App\Domain\WhatsApp\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WhatsappInstanceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'sector_id' => $this->sector_id,
            'instance_name' => $this->instance_name,
            'phone_number' => $this->phone_number,
            'status' => $this->status,
            'last_connection_at' => optional($this->last_connection_at)->toAtomString(),
            'metadata' => $this->metadata ?? [],
            'sector' => $this->whenLoaded('sector', fn (): array => [
                'id' => $this->sector?->id,
                'name' => $this->sector?->name,
                'slug' => $this->sector?->slug,
            ]),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
