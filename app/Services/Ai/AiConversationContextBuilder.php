<?php

namespace App\Services\Ai;

use App\DTOs\Ai\ConversationAiRequestData;
use App\DTOs\Ai\MessageIntentRequestData;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiConversationContextBuilder
{
    public function buildConversationRequest(Conversation $conversation): ConversationAiRequestData
    {
        $conversation->loadMissing(['contact', 'sector']);

        $messages = $this->loadRecentMessages($conversation, (int) config('ai.conversation_history_limit', 30));

        return new ConversationAiRequestData(
            transcript: $this->buildTranscript($messages),
            messageCount: $messages->count(),
            contactName: $conversation->contact?->name,
            sectorName: $conversation->sector?->name,
        );
    }

    public function buildMessageIntentRequest(Message $message): MessageIntentRequestData
    {
        $message->loadMissing('conversation');

        $conversation = $message->conversation;

        if (! $conversation instanceof Conversation) {
            return new MessageIntentRequestData(
                messageBody: (string) $message->body,
                transcript: '',
            );
        }

        $messages = $this->loadRecentMessages($conversation, 10);

        return new MessageIntentRequestData(
            messageBody: (string) $message->body,
            transcript: $this->buildTranscript($messages),
        );
    }

    /**
     * @return Collection<int, Message>
     */
    private function loadRecentMessages(Conversation $conversation, int $limit): Collection
    {
        return $conversation->messages()
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    private function buildTranscript(Collection $messages): string
    {
        $lines = $messages
            ->filter(fn (Message $message): bool => trim((string) $message->body) !== '')
            ->map(function (Message $message): string {
                $speaker = $message->direction === Message::DIRECTION_INBOUND ? 'cliente' : 'atendente';
                $timestamp = $message->sent_at?->format('Y-m-d H:i:s')
                    ?? $message->created_at?->format('Y-m-d H:i:s')
                    ?? now()->format('Y-m-d H:i:s');

                return sprintf(
                    '[%s] %s: %s',
                    $timestamp,
                    $speaker,
                    Str::limit(preg_replace('/\s+/', ' ', (string) $message->body) ?? '', 500, '...'),
                );
            })
            ->values()
            ->all();

        return implode("\n", $lines);
    }
}
