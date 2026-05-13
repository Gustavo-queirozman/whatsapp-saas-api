<?php

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Companies\Models\CompanyUser;
use App\Services\Companies\CompanyAttendantService;
use App\Services\Realtime\RealtimeBroadcastService;
use Illuminate\Validation\ValidationException;

class AutoAssignConversationAction
{
    public function __construct(
        private readonly CompanyAttendantService $companyAttendantService,
        private readonly RealtimeBroadcastService $realtimeBroadcastService,
    ) {
    }

    public function execute(Conversation $conversation): Conversation
    {
        if ($conversation->status !== Conversation::STATUS_WAITING) {
            throw ValidationException::withMessages([
                'conversation' => 'Somente conversas aguardando podem ser distribuidas automaticamente.',
            ]);
        }

        $membership = $this->companyAttendantService
            ->listBySector($conversation->company_id, $conversation->sector_id)
            ->filter(fn (CompanyUser $item): bool => $item->user !== null)
            ->first();

        if (! $membership instanceof CompanyUser) {
            throw ValidationException::withMessages([
                'conversation' => 'Nao ha atendentes disponiveis para o setor informado.',
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
