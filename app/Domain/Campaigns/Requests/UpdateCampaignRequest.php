<?php

namespace App\Domain\Campaigns\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');

        return [
            'whatsapp_instance_id' => [
                'required',
                'integer',
                Rule::exists('whatsapp_instances', 'id')->where('company_id', $companyId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:4000'],
            'send_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:120'],
        ];
    }
}
