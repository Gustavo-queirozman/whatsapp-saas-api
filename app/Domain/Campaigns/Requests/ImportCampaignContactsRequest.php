<?php

namespace App\Domain\Campaigns\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCampaignContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contacts' => ['required', 'array', 'min:1', 'max:1000'],
            'contacts.*.name' => ['nullable', 'string', 'max:150'],
            'contacts.*.phone' => ['required', 'string', 'max:30', 'distinct'],
        ];
    }
}
