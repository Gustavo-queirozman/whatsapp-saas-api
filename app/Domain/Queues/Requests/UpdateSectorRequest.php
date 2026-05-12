<?php

namespace App\Domain\Queues\Requests;

use App\Domain\Queues\Models\Sector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');
        /** @var Sector $sector */
        $sector = $this->route('sector');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sectors', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($sector->id),
            ],
            'slug' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sectors', 'slug')
                    ->where('company_id', $companyId)
                    ->ignore($sector->id),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
