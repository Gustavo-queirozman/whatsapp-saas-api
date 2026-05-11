<?php

namespace App\Domain\Auth\Resources;

use App\Domain\Companies\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentCompany = $this->currentCompany();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'email_verified_at' => optional($this->email_verified_at)->toAtomString(),
            'current_company' => $currentCompany ? (new CompanyResource($currentCompany))->resolve($request) : null,
            'companies' => $this->relationLoaded('companies')
                ? CompanyResource::collection($this->companies)->resolve($request)
                : [],
            'created_at' => optional($this->created_at)->toAtomString(),
        ];
    }
}
