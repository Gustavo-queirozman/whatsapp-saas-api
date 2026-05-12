<?php

namespace App\Domain\Conversations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignConversationUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
        ];
    }
}
