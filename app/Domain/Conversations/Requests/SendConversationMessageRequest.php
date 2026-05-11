<?php

namespace App\Domain\Conversations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'options' => ['nullable', 'array'],
        ];
    }
}
