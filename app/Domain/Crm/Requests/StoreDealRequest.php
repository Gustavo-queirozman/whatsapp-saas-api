<?php

namespace App\Domain\Crm\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');
        $pipelineId = (int) $this->input('pipeline_id');

        return [
            'pipeline_id' => [
                'required',
                'integer',
                Rule::exists('pipelines', 'id')->where('company_id', $companyId),
            ],
            'pipeline_stage_id' => [
                'required',
                'integer',
                Rule::exists('pipeline_stages', 'id')
                    ->where('company_id', $companyId)
                    ->where('pipeline_id', $pipelineId),
            ],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where('company_id', $companyId),
            ],
            'assigned_user_id' => [
                'required',
                'integer',
                Rule::exists('company_users', 'user_id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true),
            ],
            'title' => ['required', 'string', 'max:200'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
