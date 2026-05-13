<?php

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Models\Conversation;
use App\Services\Realtime\RealtimeBroadcastService;

class CloseConversationAction
{
    public function __construct(
        private readonly RealtimeBroadcastService $realtimeBroadcastService,
    ) {
    }

    public function execute(Conversation $conversation): Conversation
    {
        $conversation->forceFill([
            'status' => Conversation::STATUS_CLOSED,
            'closed_at' => now(),
        ])->save();

        $conversation = $conversation->fresh([
            'contact',
            'sector',
            'whatsappInstance',
            'assignedUser',
        ]) ?? $conversation->loadMissing([
            'contact',
            'sector',
            'whatsappInstance',
            'assignedUser',
        ]);

        $this->realtimeBroadcastService->broadcastConversationUpdated($conversation);

        return $conversation;
    }
}
