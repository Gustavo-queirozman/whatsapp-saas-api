<?php

namespace App\Domain\Chatbot\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BotFlowOptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'bot_flow_id' => $this->bot_flow_id,
            'target_sector_id' => $this->target_sector_id,
            'label' => $this->label,
            'number' => $this->number,
            'keywords' => $this->keywords ?? [],
            'action' => $this->action,
            'response_message' => $this->response_message,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'settings' => $this->settings ?? [],
            'target_sector' => $this->whenLoaded('targetSector', fn (): ?array => $this->targetSector === null
                ? null
                : [
                    'id' => $this->targetSector->id,
                    'name' => $this->targetSector->name,
                    'slug' => $this->targetSector->slug,
                    'color' => $this->targetSector->color,
                ]),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
