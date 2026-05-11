<?php

namespace App\Domain\WhatsApp\Actions;

use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\DTOs\WhatsApp\EvolutionWebhookPayloadData;
use App\DTOs\WhatsApp\ProcessEvolutionWebhookResultData;
use App\Services\WhatsApp\CompanyWorkspaceResolver;
use App\Services\WhatsApp\EvolutionWebhookLogger;
use App\Support\CurrentCompany;
use Illuminate\Support\Carbon;
use RuntimeException;

class ProcessEvolutionWebhookAction
{
    public function __construct(
        private readonly CompanyWorkspaceResolver $workspaceResolver,
        private readonly EvolutionWebhookLogger $logger,
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    public function execute(EvolutionWebhookPayloadData $payload): ProcessEvolutionWebhookResultData
    {
        $this->logger->received($payload);

        if ($payload->instanceName === null) {
            $this->logger->rejected($payload, 'instance_name ausente');

            return ProcessEvolutionWebhookResultData::rejected('Instance name nao informado no webhook.');
        }

        $whatsappInstance = WhatsappInstance::query()
            ->with(['company', 'sector'])
            ->where('instance_name', $payload->instanceName)
            ->first();

        if ($whatsappInstance === null) {
            $this->logger->rejected($payload, 'instancia nao encontrada');

            return ProcessEvolutionWebhookResultData::rejected('Instancia do WhatsApp nao encontrada.', 404);
        }

        $previousCompany = $this->currentCompany->get();
        $this->currentCompany->set($whatsappInstance->company);

        try {
            if ($payload->fromMe) {
                $this->logger->ignored(
                    $payload,
                    'mensagem enviada pela propria instancia',
                    $whatsappInstance->company_id,
                    $whatsappInstance->sector_id,
                );

                return ProcessEvolutionWebhookResultData::ignored('Mensagem enviada pela propria instancia foi ignorada.');
            }

            if ($payload->contactPhone === null) {
                $this->logger->ignored(
                    $payload,
                    'contato sem telefone valido',
                    $whatsappInstance->company_id,
                    $whatsappInstance->sector_id,
                );

                return ProcessEvolutionWebhookResultData::ignored('Webhook sem telefone de contato valido.');
            }

            $existingMessage = $payload->externalId === null
                ? null
                : Message::query()->where('external_id', $payload->externalId)->first();

            if ($existingMessage !== null) {
                $this->logger->duplicate(
                    $payload,
                    $existingMessage->id,
                    $whatsappInstance->company_id,
                    $whatsappInstance->sector_id,
                );

                return ProcessEvolutionWebhookResultData::duplicate($existingMessage->id);
            }

            try {
                $workspace = $this->workspaceResolver->resolveDefault($whatsappInstance->company_id);
            } catch (RuntimeException $exception) {
                $this->logger->rejected($payload, 'workspace padrao nao configurado');

                return ProcessEvolutionWebhookResultData::rejected($exception->getMessage());
            }

            $contact = $this->upsertContact($payload, $whatsappInstance->company_id, $workspace->id);
            $conversation = $this->upsertConversation($whatsappInstance, $contact->id);
            $message = $this->createMessage($payload, $conversation->id, $whatsappInstance->company_id);

            $conversation->forceFill([
                'status' => $conversation->status === Conversation::STATUS_OPEN
                    ? Conversation::STATUS_OPEN
                    : Conversation::STATUS_WAITING,
                'last_message_at' => $message->sent_at ?? Carbon::now(),
            ])->save();

            $this->logger->processed(
                $payload,
                $whatsappInstance->company_id,
                $whatsappInstance->sector_id,
                $message->id,
            );

            return ProcessEvolutionWebhookResultData::processed(
                $contact->id,
                $conversation->id,
                $message->id,
            );
        } finally {
            $this->currentCompany->set($previousCompany);
        }
    }

    private function upsertContact(EvolutionWebhookPayloadData $payload, int $companyId, int $workspaceId): Contact
    {
        $contact = Contact::query()->firstOrCreate(
            [
                'phone' => $payload->contactPhone,
            ],
            [
                'company_id' => $companyId,
                'workspace_id' => $workspaceId,
                'name' => $payload->contactName,
                'metadata' => [
                    'remote_jid' => $payload->remoteJid,
                ],
            ],
        );

        $contactMetadata = is_array($contact->metadata) ? $contact->metadata : [];
        $updatedMetadata = $contactMetadata;

        if ($payload->remoteJid !== null) {
            $updatedMetadata['remote_jid'] = $payload->remoteJid;
        }

        $dirty = false;

        if ($payload->contactName !== null && $payload->contactName !== $contact->name) {
            $contact->name = $payload->contactName;
            $dirty = true;
        }

        if ($updatedMetadata !== $contactMetadata) {
            $contact->metadata = $updatedMetadata;
            $dirty = true;
        }

        if ($contact->workspace_id !== $workspaceId) {
            $contact->workspace_id = $workspaceId;
            $dirty = true;
        }

        if ($dirty) {
            $contact->save();
        }

        return $contact;
    }

    private function upsertConversation(WhatsappInstance $whatsappInstance, int $contactId): Conversation
    {
        $conversation = Conversation::query()->firstOrCreate(
            [
                'whatsapp_instance_id' => $whatsappInstance->id,
                'contact_id' => $contactId,
            ],
            [
                'company_id' => $whatsappInstance->company_id,
                'sector_id' => $whatsappInstance->sector_id,
                'status' => Conversation::STATUS_WAITING,
            ],
        );

        $dirty = false;

        if ($conversation->sector_id !== $whatsappInstance->sector_id) {
            $conversation->sector_id = $whatsappInstance->sector_id;
            $dirty = true;
        }

        if ($conversation->status === Conversation::STATUS_CLOSED) {
            $conversation->status = Conversation::STATUS_WAITING;
            $dirty = true;
        }

        if ($dirty) {
            $conversation->save();
        }

        return $conversation;
    }

    private function createMessage(EvolutionWebhookPayloadData $payload, int $conversationId, int $companyId): Message
    {
        return Message::query()->create([
            'company_id' => $companyId,
            'conversation_id' => $conversationId,
            'direction' => Message::DIRECTION_INBOUND,
            'type' => $payload->messageType,
            'external_id' => $payload->externalId,
            'body' => $payload->body,
            'payload' => $payload->payload,
            'sent_at' => $payload->sentAt?->toDateTimeString(),
        ]);
    }
}
