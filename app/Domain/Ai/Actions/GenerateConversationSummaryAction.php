<?php

namespace App\Domain\Ai\Actions;

use App\DTOs\Ai\AiExecutionResultData;
use App\Domain\Conversations\Models\Conversation;
use App\Models\User;
use App\Services\Ai\AiConversationContextBuilder;
use App\Services\Ai\AiService;
use Illuminate\Validation\ValidationException;

class GenerateConversationSummaryAction
{
    public function __construct(
        private readonly AiConversationContextBuilder $contextBuilder,
        private readonly AiService $aiService,
    ) {
    }

    public function execute(Conversation $conversation, ?User $user = null): AiExecutionResultData
    {
        $context = $this->contextBuilder->buildConversationRequest($conversation);

        if (trim($context->transcript) === '') {
            throw ValidationException::withMessages([
                'conversation' => 'A conversa nao possui mensagens com conteudo textual para resumir.',
            ]);
        }

        return $this->aiService->summarizeConversation($conversation, $context, $user?->id);
    }
}
