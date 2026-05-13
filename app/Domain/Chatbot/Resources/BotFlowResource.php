<?php

namespace App\Domain\Chatbot\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BotFlowResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'sector_id' => $this->sector_id,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'welcome_message' => $this->welcome_message,
            'menu_message' => $this->menu_message,
            'invalid_option_message' => $this->invalid_option_message,
            'out_of_hours_message' => $this->out_of_hours_message,
            'office_hours_enabled' => (bool) $this->office_hours_enabled,
            'office_hours_timezone' => $this->office_hours_timezone,
            'office_hours' => $this->office_hours ?? [],
            'settings' => $this->settings ?? [],
            'sector' => $this->whenLoaded('sector', fn (): ?array => $this->sector === null
                ? null
                : [
                    'id' => $this->sector->id,
                    'name' => $this->sector->name,
                    'slug' => $this->sector->slug,
                    'color' => $this->sector->color,
                ]),
            'options' => $this->whenLoaded(
                'options',
                fn () => BotFlowOptionResource::collection($this->options)->resolve($request)
            ),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
