<?php

namespace App\Domain\Queues\Actions;

use App\Domain\Queues\Models\Sector;

class DetachSectorUserAction
{
    public function execute(Sector $sector, int $userId): Sector
    {
        $sector->users()->detach($userId);

        return $sector->refresh();
    }
}
