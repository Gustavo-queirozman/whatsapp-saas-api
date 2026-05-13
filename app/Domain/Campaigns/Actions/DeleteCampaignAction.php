<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Models\Campaign;

class DeleteCampaignAction
{
    public function execute(Campaign $campaign): void
    {
        $campaign->delete();
    }
}
