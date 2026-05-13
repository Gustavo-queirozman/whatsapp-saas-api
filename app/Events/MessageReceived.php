<?php

namespace App\Events;

use App\Domain\Conversations\Models\Message;
use App\Domain\Conversations\Resources\MessageResource;
use App\Support\Broadcasting\ChannelNames;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(ChannelNames::companyConversations($this->message->company_id)),
            new PrivateChannel(ChannelNames::conversation(
                $this->message->company_id,
                $this->message->conversation_id,
            )),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->message->company_id,
            'conversation_id' => $this->message->conversation_id,
            'message' => (new MessageResource($this->message))->resolve(),
        ];
    }
}
