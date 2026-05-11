<?php

namespace App\Domain\Tenants\Resources;

use App\Domain\WhatsApp\Resources\WhatsappInstanceResource;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'whatsapp_instances' => WhatsappInstanceResource::collection($this->whenLoaded('whatsappInstances')),
        ];
    }
}
