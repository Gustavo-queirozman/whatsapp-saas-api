<?php

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Models\Conversation;

class CloseConversationAction
{
    public function execute(Conversation $conversation): Conversation
    {
        $conversation->forceFill([
            'status' => Conversation::STATUS_CLOSED,
            'closed_at' => now(),
        ])->save();

        return $conversation->fresh([
            'contact',
            'sector',
            'whatsappInstance',
            'assignedUser',
        ]);
    }
}
