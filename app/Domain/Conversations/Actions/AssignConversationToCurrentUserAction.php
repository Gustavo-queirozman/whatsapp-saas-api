<?php

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Models\Conversation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssignConversationToCurrentUserAction
{
    public function execute(Conversation $conversation, User $user): Conversation
    {
        if ($conversation->status !== Conversation::STATUS_WAITING) {
            throw ValidationException::withMessages([
                'conversation' => 'Somente conversas aguardando podem ser assumidas.',
            ]);
        }

        $conversation->forceFill([
            'assigned_user_id' => $user->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_at' => now(),
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
