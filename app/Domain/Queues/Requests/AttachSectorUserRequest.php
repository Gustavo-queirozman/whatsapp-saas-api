<?php

namespace App\Domain\Queues\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachSectorUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
