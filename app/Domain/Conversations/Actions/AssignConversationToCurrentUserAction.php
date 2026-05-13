<?php

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Models\Conversation;
use App\Models\User;
use App\Services\Companies\CompanyAttendantService;
use App\Services\Realtime\RealtimeBroadcastService;
use Illuminate\Validation\ValidationException;

class AssignConversationToCurrentUserAction
{
    public function __construct(
        private readonly CompanyAttendantService $companyAttendantService,
        private readonly RealtimeBroadcastService $realtimeBroadcastService,
    ) {
    }

    public function execute(Conversation $conversation, User $user): Conversation
    {
        if ($conversation->status !== Conversation::STATUS_WAITING) {
            throw ValidationException::withMessages([
                'conversation' => 'Somente conversas aguardando podem ser assumidas.',
            ]);
        }

        $membership = $this->companyAttendantService->findBySectorAndUser(
            $conversation->company_id,
            $conversation->sector_id,
            $user->id,
        );

        if ($membership === null) {
            throw ValidationException::withMessages([
                'conversation' => 'Voce nao pertence ao setor desta conversa.',
            ]);
        }

        $conversation->forceFill([
            'assigned_user_id' => $user->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_at' => now(),
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

        $this->realtimeBroadcastService->broadcastConversationAssigned($conversation);

        return $conversation;
    }
}
