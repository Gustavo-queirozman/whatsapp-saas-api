<?php

namespace App\Domain\Queues\Actions;

use App\Domain\Queues\Models\Sector;
use App\Services\Companies\CompanyAttendantService;
use Illuminate\Validation\ValidationException;

class AttachSectorUserAction
{
    public function __construct(
        private readonly CompanyAttendantService $companyAttendantService,
    ) {
    }

    public function execute(Sector $sector, int $userId): Sector
    {
        $membership = $this->companyAttendantService->findByCompanyAndUser(
            $sector->company_id,
            $userId,
        );

        if ($membership === null) {
            throw ValidationException::withMessages([
                'user_id' => 'O usuario informado nao pode atender conversas desta empresa.',
            ]);
        }

        $sector->users()->syncWithoutDetaching([
            $membership->user_id => [
                'company_id' => $sector->company_id,
            ],
        ]);

        return $sector->refresh();
    }
}
