<?php

namespace App\Events;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Resources\ConversationResource;
use App\Support\Broadcasting\ChannelNames;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationAssigned implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(ChannelNames::companyConversations($this->conversation->company_id)),
            new PrivateChannel(ChannelNames::conversation(
                $this->conversation->company_id,
                $this->conversation->id,
            )),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.assigned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->conversation->company_id,
            'conversation' => (new ConversationResource($this->conversation))->resolve(),
        ];
    }
}
