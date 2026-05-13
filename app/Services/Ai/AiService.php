<?php

namespace App\Services\Ai;

use App\DTOs\Ai\AiExecutionResultData;
use App\DTOs\Ai\AiProviderResponseData;
use App\DTOs\Ai\ConversationAiRequestData;
use App\DTOs\Ai\MessageIntentRequestData;
use App\Domain\Ai\Models\AiUsage;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use Throwable;

class AiService
{
    public function __construct(
        private readonly AiProviderInterface $provider,
    ) {
    }

    public function summarizeConversation(
        Conversation $conversation,
        ConversationAiRequestData $data,
        ?int $userId = null
    ): AiExecutionResultData {
        return $this->execute(
            operation: 'summary',
            companyId: (int) $conversation->company_id,
            conversationId: (int) $conversation->id,
            messageId: null,
            userId: $userId,
            requestPayload: [
                'message_count' => $data->messageCount,
                'contact_name' => $data->contactName,
                'sector_name' => $data->sectorName,
                'transcript' => $data->transcript,
            ],
            callback: fn (): AiProviderResponseData => $this->provider->summarizeConversation($data),
        );
    }

    public function suggestReply(
        Conversation $conversation,
        ConversationAiRequestData $data,
        ?int $userId = null
    ): AiExecutionResultData {
        return $this->execute(
            operation: 'suggest_reply',
            companyId: (int) $conversation->company_id,
            conversationId: (int) $conversation->id,
            messageId: null,
            userId: $userId,
            requestPayload: [
                'message_count' => $data->messageCount,
                'contact_name' => $data->contactName,
                'sector_name' => $data->sectorName,
                'transcript' => $data->transcript,
            ],
            callback: fn (): AiProviderResponseData => $this->provider->suggestReply($data),
        );
    }

    public function classifyIntent(
        Message $message,
        MessageIntentRequestData $data,
        ?int $userId = null
    ): AiExecutionResultData {
        return $this->execute(
            operation: 'classify_intent',
            companyId: (int) $message->company_id,
            conversationId: (int) $message->conversation_id,
            messageId: (int) $message->id,
            userId: $userId,
            requestPayload: [
                'message_body' => $data->messageBody,
                'transcript' => $data->transcript,
            ],
            callback: fn (): AiProviderResponseData => $this->provider->classifyIntent($data),
        );
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     */
    private function execute(
        string $operation,
        int $companyId,
        ?int $conversationId,
        ?int $messageId,
        ?int $userId,
        array $requestPayload,
        callable $callback,
    ): AiExecutionResultData {
        try {
            $response = $callback();

            $usage = AiUsage::query()->create([
                'company_id' => $companyId,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'user_id' => $userId,
                'provider' => $this->provider->name(),
                'model' => $response->model,
                'operation' => $operation,
                'status' => 'success',
                'result' => $response->content,
                'request_payload' => $requestPayload,
                'response_payload' => $response->raw,
                'prompt_tokens' => $response->promptTokens,
                'completion_tokens' => $response->completionTokens,
                'total_tokens' => $response->totalTokens,
            ]);

            return new AiExecutionResultData(
                content: $response->content,
                provider: $this->provider->name(),
                model: $response->model,
                usageId: (int) $usage->id,
                promptTokens: $response->promptTokens,
                completionTokens: $response->completionTokens,
                totalTokens: $response->totalTokens,
            );
        } catch (Throwable $exception) {
            AiUsage::query()->create([
                'company_id' => $companyId,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'user_id' => $userId,
                'provider' => $this->provider->name(),
                'operation' => $operation,
                'status' => 'error',
                'request_payload' => $requestPayload,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
