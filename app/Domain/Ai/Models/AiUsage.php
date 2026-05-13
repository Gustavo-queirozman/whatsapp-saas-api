<?php

namespace App\Domain\Ai\Models;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'conversation_id',
        'message_id',
        'user_id',
        'provider',
        'model',
        'operation',
        'status',
        'result',
        'request_payload',
        'response_payload',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
