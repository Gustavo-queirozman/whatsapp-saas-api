<?php

namespace App\Domain\Crm\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'pipeline_id' => $this->pipeline_id,
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'contact_id' => $this->contact_id,
            'assigned_user_id' => $this->assigned_user_id,
            'title' => $this->title,
            'value' => $this->value,
            'notes' => $this->notes,
            'pipeline' => $this->whenLoaded('pipeline', fn (): array => [
                'id' => $this->pipeline->id,
                'name' => $this->pipeline->name,
            ]),
            'stage' => $this->whenLoaded('stage', fn (): array => [
                'id' => $this->stage->id,
                'pipeline_id' => $this->stage->pipeline_id,
                'name' => $this->stage->name,
                'color' => $this->stage->color,
                'position' => $this->stage->position,
            ]),
            'contact' => $this->whenLoaded('contact', fn (): ?array => $this->contact === null ? null : [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'phone' => $this->contact->phone,
            ]),
            'assigned_user' => $this->whenLoaded('assignedUser', fn (): ?array => $this->assignedUser === null ? null : [
                'id' => $this->assignedUser->id,
                'name' => $this->assignedUser->name,
                'email' => $this->assignedUser->email,
            ]),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
