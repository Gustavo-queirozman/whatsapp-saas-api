<?php

namespace App\Domain\Chatbot\Actions;

use App\Domain\Chatbot\Models\BotFlow;

class DeleteBotFlowAction
{
    public function execute(BotFlow $botFlow): void
    {
        $botFlow->delete();
    }
}
