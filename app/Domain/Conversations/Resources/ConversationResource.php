<?php

namespace App\Domain\Conversations\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'sector_id' => $this->sector_id,
            'whatsapp_instance_id' => $this->whatsapp_instance_id,
            'contact_id' => $this->contact_id,
            'assigned_user_id' => $this->assigned_user_id,
            'status' => $this->status,
            'assigned_at' => optional($this->assigned_at)->toAtomString(),
            'closed_at' => optional($this->closed_at)->toAtomString(),
            'last_message_at' => optional($this->last_message_at)->toAtomString(),
            'messages_count' => $this->whenCounted('messages'),
            'contact' => $this->whenLoaded('contact', fn (): array => [
                'id' => $this->contact?->id,
                'name' => $this->contact?->name,
                'phone' => $this->contact?->phone,
                'avatar_url' => $this->contact?->avatar_url,
                'metadata' => $this->contact?->metadata ?? [],
            ]),
            'sector' => $this->whenLoaded('sector', fn (): array => [
                'id' => $this->sector?->id,
                'name' => $this->sector?->name,
                'slug' => $this->sector?->slug,
                'color' => $this->sector?->color,
            ]),
            'whatsapp_instance' => $this->whenLoaded('whatsappInstance', fn (): array => [
                'id' => $this->whatsappInstance?->id,
                'instance_name' => $this->whatsappInstance?->instance_name,
                'phone_number' => $this->whatsappInstance?->phone_number,
                'status' => $this->whatsappInstance?->status,
            ]),
            'assigned_user' => $this->whenLoaded('assignedUser', fn (): ?array => $this->assignedUser === null
                ? null
                : [
                    'id' => $this->assignedUser->id,
                    'name' => $this->assignedUser->name,
                    'email' => $this->assignedUser->email,
                ]),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values()->all()),
            'created_at' => optional($this->created_at)->toAtomString(),
            'updated_at' => optional($this->updated_at)->toAtomString(),
        ];
    }
}
