<?php

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Queues\Models\Sector;
use App\Services\Realtime\RealtimeBroadcastService;

class TransferConversationSectorAction
{
    public function __construct(
        private readonly RealtimeBroadcastService $realtimeBroadcastService,
    ) {
    }

    public function execute(Conversation $conversation, Sector $sector): Conversation
    {
        $conversation->forceFill([
            'sector_id' => $sector->id,
            'assigned_user_id' => null,
            'status' => Conversation::STATUS_WAITING,
            'assigned_at' => null,
            'closed_at' => null,
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
