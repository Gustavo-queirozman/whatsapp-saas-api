<?php

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Models\Conversation;
use App\Services\Companies\CompanyAttendantService;
use Illuminate\Validation\ValidationException;

class AssignConversationToUserAction
{
    public function __construct(
        private readonly CompanyAttendantService $companyAttendantService,
    ) {
    }

    public function execute(Conversation $conversation, int $userId): Conversation
    {
        $membership = $this->companyAttendantService->findByCompanyAndUser(
            $conversation->company_id,
            $userId,
        );

        if ($membership === null) {
            throw ValidationException::withMessages([
                'user_id' => 'O usuario informado nao pode atender conversas desta empresa.',
            ]);
        }

        $conversation->forceFill([
            'assigned_user_id' => $membership->user_id,
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
