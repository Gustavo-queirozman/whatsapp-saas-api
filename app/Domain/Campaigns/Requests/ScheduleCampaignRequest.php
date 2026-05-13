<?php

namespace App\Domain\Campaigns\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
