<?php

namespace App\Services\Realtime;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Events\ConversationAssigned;
use App\Events\ConversationUpdated;
use App\Events\InstanceStatusChanged;
use App\Events\MessageReceived;

class RealtimeBroadcastService
{
    public function broadcastConversationUpdated(Conversation $conversation): void
    {
        ConversationUpdated::dispatch($this->prepareConversation($conversation));
    }

    public function broadcastConversationAssigned(Conversation $conversation): void
    {
        $conversation = $this->prepareConversation($conversation);

        ConversationAssigned::dispatch($conversation);
        ConversationUpdated::dispatch($conversation);
    }

    public function broadcastMessageReceived(Message $message): void
    {
        MessageReceived::dispatch($this->prepareMessage($message));
    }

    public function broadcastInstanceStatusChanged(WhatsappInstance $whatsappInstance): void
    {
        InstanceStatusChanged::dispatch($this->prepareWhatsappInstance($whatsappInstance));
    }

    private function prepareConversation(Conversation $conversation): Conversation
    {
        $refreshed = $conversation->fresh([
            'contact',
            'sector',
            'whatsappInstance',
            'assignedUser',
            'tags',
        ]);

        if ($refreshed instanceof Conversation) {
            $refreshed->loadCount('messages');

            return $refreshed;
        }

        $conversation->loadMissing([
            'contact',
            'sector',
            'whatsappInstance',
            'assignedUser',
            'tags',
        ])->loadCount('messages');

        return $conversation;
    }

    private function prepareMessage(Message $message): Message
    {
        $refreshed = $message->fresh();

        if ($refreshed instanceof Message) {
            return $refreshed;
        }

        return $message;
    }

    private function prepareWhatsappInstance(WhatsappInstance $whatsappInstance): WhatsappInstance
    {
        $refreshed = $whatsappInstance->fresh(['sector']);

        if ($refreshed instanceof WhatsappInstance) {
            return $refreshed;
        }

        $whatsappInstance->loadMissing('sector');

        return $whatsappInstance;
    }
}
