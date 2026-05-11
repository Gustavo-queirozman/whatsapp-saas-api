<?php

namespace App\DTOs\Conversations;

use App\Domain\Conversations\Requests\SendConversationMessageRequest;

class SendConversationMessageData
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $body,
        public readonly array $options = [],
    ) {
    }

    public static function fromRequest(SendConversationMessageRequest $request): self
    {
        /** @var array<string, mixed>|null $options */
        $options = $request->validated('options');

        return new self(
            body: (string) $request->validated('body'),
            options: $options ?? [],
        );
    }
}
