<?php

namespace App\Domain\Conversations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferConversationSectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');

        return [
            'sector_id' => [
                'required',
                'integer',
                Rule::exists('sectors', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}
