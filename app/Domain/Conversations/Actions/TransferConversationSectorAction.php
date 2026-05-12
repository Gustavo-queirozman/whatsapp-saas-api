<?php

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Queues\Models\Sector;

class TransferConversationSectorAction
{
    public function execute(Conversation $conversation, Sector $sector): Conversation
    {
        $conversation->forceFill([
            'sector_id' => $sector->id,
            'assigned_user_id' => null,
            'status' => Conversation::STATUS_WAITING,
            'assigned_at' => null,
            'closed_at' => null,
        ])->save();

        return $conversation->fresh([
            'contact',
            'sector',
            'whatsappInstance',
            'assignedUser',
        ]);
    }
}
