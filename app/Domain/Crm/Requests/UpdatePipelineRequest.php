<?php

namespace App\Domain\Crm\Requests;

use App\Domain\Crm\Models\Pipeline;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');
        $pipeline = $this->route('pipeline');
        $pipelineId = $pipeline instanceof Pipeline ? $pipeline->getKey() : null;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('pipelines', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($pipelineId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
