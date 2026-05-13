<?php

namespace App\Events;

use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Domain\WhatsApp\Resources\WhatsappInstanceResource;
use App\Support\Broadcasting\ChannelNames;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstanceStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly WhatsappInstance $whatsappInstance,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(ChannelNames::whatsappInstances($this->whatsappInstance->company_id)),
            new PrivateChannel(ChannelNames::whatsappInstance(
                $this->whatsappInstance->company_id,
                $this->whatsappInstance->id,
            )),
        ];
    }

    public function broadcastAs(): string
    {
        return 'instance.status-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->whatsappInstance->company_id,
            'whatsapp_instance' => (new WhatsappInstanceResource($this->whatsappInstance))->resolve(),
        ];
    }
}
