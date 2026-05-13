<?php

namespace App\Domain\Conversations\Actions;

use App\DTOs\Conversations\SendConversationMessageData;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Services\EvolutionGateway\EvolutionClient;
use App\Services\EvolutionGateway\EvolutionMessageMetadataResolver;
use App\Services\Realtime\RealtimeBroadcastService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendConversationMessageAction
{
    public function __construct(
        private readonly EvolutionClient $evolutionClient,
        private readonly EvolutionMessageMetadataResolver $metadataResolver,
        private readonly RealtimeBroadcastService $realtimeBroadcastService,
    ) {
    }

    public function execute(Conversation $conversation, SendConversationMessageData $data): Message
    {
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
            $data->body,
            $data->options,
        );

        $message = DB::transaction(function () use ($conversation, $data, $response): Message {
            $message = $conversation->messages()->create([
                'company_id' => $conversation->company_id,
                'direction' => Message::DIRECTION_OUTBOUND,
                'type' => Message::TYPE_TEXT,
                'external_id' => $this->metadataResolver->extractExternalId($response),
                'body' => $data->body,
                'payload' => $response,
                'sent_at' => $this->metadataResolver->resolveSentAt($response)?->toDateTimeString()
                    ?? now()->toDateTimeString(),
            ]);

            $conversation->forceFill([
                'status' => Conversation::STATUS_OPEN,
                'closed_at' => null,
                'last_message_at' => $message->sent_at ?? now(),
            ])->save();

            return $message->fresh('conversation') ?? $message;
        });

        $this->realtimeBroadcastService->broadcastMessageReceived($message);
        $this->realtimeBroadcastService->broadcastConversationUpdated($conversation);

        return $message;
    }
}
