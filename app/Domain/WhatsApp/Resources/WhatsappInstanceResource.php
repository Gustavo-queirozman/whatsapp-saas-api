<?php

namespace App\Domain\WhatsApp\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WhatsappInstanceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'provider' => $this->provider,
            'phone_number' => $this->phone_number,
            'status' => $this->status,
            'connected_at' => optional($this->connected_at)->toAtomString(),
        ];
    }
}
