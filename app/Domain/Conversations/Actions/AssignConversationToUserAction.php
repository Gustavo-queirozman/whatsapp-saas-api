<?php

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Models\Conversation;
use App\Services\Companies\CompanyAttendantService;
use App\Services\Realtime\RealtimeBroadcastService;
use Illuminate\Validation\ValidationException;

class AssignConversationToUserAction
{
    public function __construct(
        private readonly CompanyAttendantService $companyAttendantService,
        private readonly RealtimeBroadcastService $realtimeBroadcastService,
    ) {
    }

    public function execute(Conversation $conversation, int $userId): Conversation
    {
        $membership = $this->companyAttendantService->findBySectorAndUser(
            $conversation->company_id,
            $conversation->sector_id,
            $userId,
        );

        if ($membership === null) {
            throw ValidationException::withMessages([
                'user_id' => 'O usuario informado nao pode atender conversas deste setor.',
            ]);
        }

        $conversation->forceFill([
            'assigned_user_id' => $membership->user_id,
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
