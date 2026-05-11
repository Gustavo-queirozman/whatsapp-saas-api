<?php

namespace App\Domain\WhatsApp\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsappInstanceRequest extends FormRequest
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
            'instance_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('whatsapp_instances', 'instance_name')->where('company_id', $companyId),
            ],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
