<?php

namespace App\Domain\Queues\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sectors', 'name')->where('company_id', $companyId),
            ],
            'slug' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sectors', 'slug')->where('company_id', $companyId),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
