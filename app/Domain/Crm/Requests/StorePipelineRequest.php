<?php

namespace App\Domain\Crm\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePipelineRequest extends FormRequest
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
                'max:150',
                Rule::unique('pipelines', 'name')->where('company_id', $companyId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
