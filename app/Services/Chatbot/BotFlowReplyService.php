<?php

namespace App\Services\Chatbot;

use App\Domain\Chatbot\Models\BotFlow;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Services\EvolutionGateway\EvolutionClient;
use App\Services\EvolutionGateway\EvolutionMessageMetadataResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BotFlowReplyService
{
    public function __construct(
        private readonly EvolutionClient $evolutionClient,
        private readonly EvolutionMessageMetadataResolver $metadataResolver,
    ) {
    }

    public function send(Conversation $conversation, BotFlow $botFlow, string $text): ?Message
    {
        $normalizedText = trim($text);

        if ($normalizedText === '') {
            return null;
        }

        $conversation->loadMissing(['contact', 'whatsappInstance']);

        $instance = $conversation->whatsappInstance;

        if (! $instance instanceof WhatsappInstance) {
            throw ValidationException::withMessages([
                'conversation' => 'A conversa nao possui uma instancia do WhatsApp vinculada.',
            ]);
        }

        $phone = (string) $conversation->contact?->phone;

        if ($phone === '') {
            throw ValidationException::withMessages([
                'conversation' => 'A conversa nao possui um contato com telefone valido.',
            ]);
        }

        $response = $this->evolutionClient->sendTextMessage(
            $instance->instance_name,
            $phone,
            $normalizedText,
        );

        return DB::transaction(function () use ($conversation, $botFlow, $normalizedText, $response): Message {
            $sentAt = $this->metadataResolver->resolveSentAt($response);

            $message = $conversation->messages()->create([
                'company_id' => $conversation->company_id,
                'direction' => Message::DIRECTION_OUTBOUND,
                'type' => Message::TYPE_TEXT,
                'external_id' => $this->metadataResolver->extractExternalId($response),
                'body' => $normalizedText,
                'payload' => array_merge($response, [
                    'automation' => [
                        'type' => 'chatbot',
                        'bot_flow_id' => $botFlow->id,
                    ],
                ]),
                'sent_at' => $sentAt?->toDateTimeString() ?? now()->toDateTimeString(),
            ]);

            $conversation->forceFill([
                'closed_at' => null,
                'last_message_at' => $message->sent_at ?? now(),
            ])->save();

            return $message->fresh('conversation');
        });
    }
}
