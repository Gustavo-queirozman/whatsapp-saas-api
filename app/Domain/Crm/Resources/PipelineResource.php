<?php

namespace App\Domain\Crm\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PipelineResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'stages_count' => $this->whenCounted('stages'),
            'deals_count' => $this->whenCounted('deals'),
            'stages' => $this->whenLoaded(
                'stages',
                fn (): array => PipelineStageResource::collection($this->stages)->resolve()
            ),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
