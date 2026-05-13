<?php

namespace App\Domain\Crm\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveDealStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');

        return [
            'pipeline_stage_id' => [
                'required',
                'integer',
                Rule::exists('pipeline_stages', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}
