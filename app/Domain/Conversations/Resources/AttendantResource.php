<?php

namespace App\Domain\Conversations\Resources;

use App\Domain\Companies\Models\CompanyUser;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyUser
 */
class AttendantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'role' => $this->role === null
                ? null
                : [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                    'slug' => $this->role->slug,
                ],
        ];
    }
}
