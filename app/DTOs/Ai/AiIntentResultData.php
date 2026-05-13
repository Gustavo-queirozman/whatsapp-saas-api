<?php

namespace App\DTOs\Ai;

class AiIntentResultData
{
    public function __construct(
        public readonly string $intent,
        public readonly string $provider,
        public readonly ?string $model,
        public readonly int $usageId,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?int $totalTokens = null,
    ) {
    }
}
