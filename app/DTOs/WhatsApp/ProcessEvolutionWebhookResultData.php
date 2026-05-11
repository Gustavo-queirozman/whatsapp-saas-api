<?php

namespace App\DTOs\WhatsApp;

class ProcessEvolutionWebhookResultData
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly string $message,
        public readonly int $httpStatus,
        public readonly ?int $contactId = null,
        public readonly ?int $conversationId = null,
        public readonly ?int $messageId = null,
    ) {
    }

    public static function processed(int $contactId, int $conversationId, int $messageId): self
    {
        return new self(
            true,
            'processed',
            'Webhook processado com sucesso.',
            201,
            $contactId,
            $conversationId,
            $messageId,
        );
    }

    public static function ignored(string $message): self
    {
        return new self(true, 'ignored', $message, 200);
    }

    public static function duplicate(?int $messageId = null): self
    {
        return new self(true, 'duplicate', 'Mensagem ja processada anteriormente.', 200, null, null, $messageId);
    }

    public static function rejected(string $message, int $httpStatus = 422): self
    {
        return new self(false, 'rejected', $message, $httpStatus);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
        ];
    }
}
