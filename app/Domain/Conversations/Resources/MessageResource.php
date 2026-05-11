<?php

namespace App\Domain\Conversations\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'conversation_id' => $this->conversation_id,
            'direction' => $this->direction,
            'type' => $this->type,
            'external_id' => $this->external_id,
            'body' => $this->body,
            'payload' => $this->payload ?? [],
            'sent_at' => optional($this->sent_at)->toAtomString(),
            'delivered_at' => optional($this->delivered_at)->toAtomString(),
            'read_at' => optional($this->read_at)->toAtomString(),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
