<?php

namespace App\DTOs\Ai;

class MessageIntentRequestData
{
    public function __construct(
        public readonly string $messageBody,
        public readonly string $transcript,
    ) {
    }
}
