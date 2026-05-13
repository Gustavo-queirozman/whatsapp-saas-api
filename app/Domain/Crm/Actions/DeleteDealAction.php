<?php

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\Deal;

class DeleteDealAction
{
    public function execute(Deal $deal): void
    {
        $deal->delete();
    }
}
