<?php

namespace App\Domain\Crm\Requests;

use App\Domain\Crm\Models\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePipelineStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');
        $pipelineId = (int) $this->input('pipeline_id');
        $stage = $this->route('pipeline_stage');
        $stageId = $stage instanceof PipelineStage ? $stage->getKey() : null;

        return [
            'pipeline_id' => [
                'required',
                'integer',
                Rule::exists('pipelines', 'id')->where('company_id', $companyId),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('pipeline_stages', 'name')
                    ->where('company_id', $companyId)
                    ->where('pipeline_id', $pipelineId)
                    ->ignore($stageId),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
