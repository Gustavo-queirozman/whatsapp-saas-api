<?php

namespace App\Domain\Auth\Resources;

use App\Domain\Tenants\Resources\TenantResource;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'email_verified_at' => optional($this->email_verified_at)->toAtomString(),
            'tenants' => TenantResource::collection($this->whenLoaded('tenants')),
            'created_at' => optional($this->created_at)->toAtomString(),
        ];
    }
}
