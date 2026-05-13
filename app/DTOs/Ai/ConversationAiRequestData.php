<?php

namespace App\DTOs\Ai;

class ConversationAiRequestData
{
    public function __construct(
        public readonly string $transcript,
        public readonly int $messageCount,
        public readonly ?string $contactName = null,
        public readonly ?string $sectorName = null,
    ) {
    }
}
