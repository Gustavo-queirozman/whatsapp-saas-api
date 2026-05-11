<?php

namespace App\Domain\Companies\Resources;

use App\Domain\WhatsApp\Resources\WhatsappInstanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'whatsapp_instances' => WhatsappInstanceResource::collection($this->whenLoaded('whatsappInstances')),
        ];
    }
}
