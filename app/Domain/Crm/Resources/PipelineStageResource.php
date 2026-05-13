<?php

namespace App\Domain\Crm\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PipelineStageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'pipeline_id' => $this->pipeline_id,
            'name' => $this->name,
            'color' => $this->color,
            'position' => $this->position,
            'deals_count' => $this->whenCounted('deals'),
            'pipeline' => $this->whenLoaded('pipeline', fn (): array => [
                'id' => $this->pipeline->id,
                'name' => $this->pipeline->name,
            ]),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
