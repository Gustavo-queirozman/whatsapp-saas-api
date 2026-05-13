<?php

namespace App\Services\Ai;

use App\DTOs\Ai\AiProviderResponseData;
use App\DTOs\Ai\ConversationAiRequestData;
use App\DTOs\Ai\MessageIntentRequestData;

interface AiProviderInterface
{
    public function name(): string;

    public function summarizeConversation(ConversationAiRequestData $data): AiProviderResponseData;

    public function suggestReply(ConversationAiRequestData $data): AiProviderResponseData;

    public function classifyIntent(MessageIntentRequestData $data): AiProviderResponseData;
}
