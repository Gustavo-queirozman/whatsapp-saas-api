<?php

namespace App\Domain\Companies\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentCompany = $request->attributes->get('current_company');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'is_current' => $currentCompany?->is($this->resource) ?? false,
            'created_at' => optional($this->created_at)->toAtomString(),
        ];
    }
}
