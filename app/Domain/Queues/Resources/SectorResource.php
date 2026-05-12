<?php

namespace App\Domain\Queues\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SectorResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'settings' => $this->settings ?? [],
            'users_count' => $this->whenCounted('users'),
            'users' => $this->whenLoaded('users', fn () => $this->users->map(fn ($user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values()->all()),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
