<?php

namespace App\Domain\Conversations\Actions;

use App\DTOs\Conversations\SendConversationMessageData;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Services\EvolutionGateway\EvolutionClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendConversationMessageAction
{
    public function __construct(
        private readonly EvolutionClient $evolutionClient,
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

        return DB::transaction(function () use ($conversation, $data, $response): Message {
            $message = $conversation->messages()->create([
                'company_id' => $conversation->company_id,
                'direction' => Message::DIRECTION_OUTBOUND,
                'type' => Message::TYPE_TEXT,
                'external_id' => $this->extractExternalId($response),
                'body' => $data->body,
                'payload' => $response,
                'sent_at' => $this->resolveSentAt($response)?->toDateTimeString() ?? now()->toDateTimeString(),
            ]);

            $conversation->forceFill([
                'status' => Conversation::STATUS_OPEN,
                'last_message_at' => $message->sent_at ?? now(),
            ])->save();

            return $message->fresh('conversation');
        });
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractExternalId(array $response): ?string
    {
        $externalId = data_get($response, 'key.id')
            ?? data_get($response, 'message.key.id')
            ?? data_get($response, 'messageId')
            ?? data_get($response, 'id');

        return is_string($externalId) && $externalId !== '' ? $externalId : null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function resolveSentAt(array $response): ?Carbon
    {
        $timestamp = data_get($response, 'messageTimestamp')
            ?? data_get($response, 'message.messageTimestamp')
            ?? data_get($response, 'timestamp');

        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp((int) $timestamp);
        }

        if (is_string($timestamp) && $timestamp !== '') {
            return Carbon::parse($timestamp);
        }

        return null;
    }
}
