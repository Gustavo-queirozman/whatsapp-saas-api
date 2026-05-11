<?php

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Models\Conversation;

class ReopenConversationAction
{
    public function execute(Conversation $conversation): Conversation
    {
        $conversation->forceFill([
            'status' => Conversation::STATUS_OPEN,
        ])->save();

        return $conversation->fresh([
            'contact',
            'sector',
            'whatsappInstance',
            'assignedUser',
        ]);
    }
}
