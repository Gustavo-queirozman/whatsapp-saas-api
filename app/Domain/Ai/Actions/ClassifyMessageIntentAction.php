<?php

namespace App\Domain\Ai\Actions;

use App\DTOs\Ai\AiIntentResultData;
use App\Domain\Conversations\Models\Message;
use App\Models\User;
use App\Services\Ai\AiConversationContextBuilder;
use App\Services\Ai\AiService;
use Illuminate\Validation\ValidationException;

class ClassifyMessageIntentAction
{
    public function __construct(
        private readonly AiConversationContextBuilder $contextBuilder,
        private readonly AiService $aiService,
    ) {
    }

    public function execute(Message $message, ?User $user = null): AiIntentResultData
    {
        if (trim((string) $message->body) === '') {
            throw ValidationException::withMessages([
                'message' => 'A mensagem nao possui conteudo textual para classificar.',
            ]);
        }

        $context = $this->contextBuilder->buildMessageIntentRequest($message);
        $result = $this->aiService->classifyIntent($message, $context, $user?->id);

        return new AiIntentResultData(
            intent: $this->normalizeIntent($result->content),
            provider: $result->provider,
            model: $result->model,
            usageId: $result->usageId,
            promptTokens: $result->promptTokens,
            completionTokens: $result->completionTokens,
            totalTokens: $result->totalTokens,
        );
    }

    private function normalizeIntent(string $content): string
    {
        $normalized = mb_strtolower(trim($content));

        foreach (['vendas', 'suporte', 'financeiro', 'outros'] as $intent) {
            if (str_contains($normalized, $intent)) {
                return $intent;
            }
        }

        return 'outros';
    }
}
