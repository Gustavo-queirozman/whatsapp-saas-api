<?php

namespace App\Domain\Tags\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');

        return [
            'tag_id' => [
                'required',
                'integer',
                Rule::exists('tags', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}
