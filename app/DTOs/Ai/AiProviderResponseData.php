<?php

namespace App\DTOs\Ai;

class AiProviderResponseData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $content,
        public readonly ?string $model = null,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?int $totalTokens = null,
        public readonly array $raw = [],
    ) {
    }
}
