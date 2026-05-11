<?php

namespace App\Services\WhatsApp;

use App\DTOs\WhatsApp\EvolutionWebhookPayloadData;
use Illuminate\Support\Facades\Log;

class EvolutionWebhookLogger
{
    public function received(EvolutionWebhookPayloadData $payload): void
    {
        $this->log('info', 'Webhook Evolution recebido.', $payload);
    }

    public function processed(EvolutionWebhookPayloadData $payload, int $companyId, int $sectorId, int $messageId): void
    {
        $this->log('info', 'Webhook Evolution processado.', $payload, [
            'company_id' => $companyId,
            'sector_id' => $sectorId,
            'message_id' => $messageId,
        ]);
    }

    public function ignored(EvolutionWebhookPayloadData $payload, string $reason, ?int $companyId = null, ?int $sectorId = null): void
    {
        $this->log('info', 'Webhook Evolution ignorado.', $payload, [
            'reason' => $reason,
            'company_id' => $companyId,
            'sector_id' => $sectorId,
        ]);
    }

    public function duplicate(EvolutionWebhookPayloadData $payload, int $messageId, int $companyId, int $sectorId): void
    {
        $this->log('info', 'Webhook Evolution duplicado.', $payload, [
            'company_id' => $companyId,
            'sector_id' => $sectorId,
            'message_id' => $messageId,
        ]);
    }

    public function rejected(EvolutionWebhookPayloadData $payload, string $reason): void
    {
        $this->log('warning', 'Webhook Evolution rejeitado.', $payload, [
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, EvolutionWebhookPayloadData $payload, array $context = []): void
    {
        Log::channel((string) config('evolution.webhook_log_channel', 'evolution_webhooks'))
            ->{$level}($message, array_merge([
                'event' => $payload->event,
                'instance_name' => $payload->instanceName,
                'external_id' => $payload->externalId,
                'remote_jid' => $payload->remoteJid,
                'message_type' => $payload->messageType,
                'from_me' => $payload->fromMe,
                'payload' => $payload->payload,
            ], $context));
    }
}
