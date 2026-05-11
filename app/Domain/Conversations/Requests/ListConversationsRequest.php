<?php

namespace App\Domain\Conversations\Requests;

use App\Domain\Conversations\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('current_company_id');

        return [
            'status' => ['nullable', 'string', Rule::in([
                Conversation::STATUS_WAITING,
                Conversation::STATUS_OPEN,
                Conversation::STATUS_CLOSED,
            ])],
            'sector_id' => [
                'nullable',
                'integer',
                Rule::exists('sectors', 'id')->where('company_id', $companyId),
            ],
            'whatsapp_instance_id' => [
                'nullable',
                'integer',
                Rule::exists('whatsapp_instances', 'id')->where('company_id', $companyId),
            ],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where('company_id', $companyId),
            ],
            'assigned_user_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:150'],
        ];
    }
}
