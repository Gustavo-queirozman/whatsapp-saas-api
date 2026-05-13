<?php

namespace App\Support\Broadcasting;

final class ChannelNames
{
    private function __construct()
    {
    }

    public static function companyConversations(int $companyId): string
    {
        return sprintf('companies.%d.conversations', $companyId);
    }

    public static function conversation(int $companyId, int $conversationId): string
    {
        return sprintf('companies.%d.conversations.%d', $companyId, $conversationId);
    }

    public static function whatsappInstances(int $companyId): string
    {
        return sprintf('companies.%d.whatsapp.instances', $companyId);
    }

    public static function whatsappInstance(int $companyId, int $instanceId): string
    {
        return sprintf('companies.%d.whatsapp.instances.%d', $companyId, $instanceId);
    }
}
