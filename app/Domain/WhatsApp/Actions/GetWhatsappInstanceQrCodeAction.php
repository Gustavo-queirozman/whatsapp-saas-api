<?php

namespace App\Domain\WhatsApp\Actions;

use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Services\EvolutionGateway\EvolutionClient;
use App\Services\WhatsApp\WhatsappInstanceStatusService;

class GetWhatsappInstanceQrCodeAction
{
    public function __construct(
        private readonly EvolutionClient $evolutionClient,
        private readonly WhatsappInstanceStatusService $statusService,
    ) {
    }

    /**
     * @return array{instance: WhatsappInstance, qrcode: array<string, mixed>, status: array<string, mixed>}
     */
    public function execute(WhatsappInstance $whatsappInstance): array
    {
        $qrcode = $this->evolutionClient->getQrCode(
            $whatsappInstance->instance_name,
            $whatsappInstance->phone_number,
        );

        $status = $this->evolutionClient->getInstanceStatus($whatsappInstance->instance_name);

        return [
            'instance' => $this->statusService->syncFromGateway($whatsappInstance, $status),
            'qrcode' => $qrcode,
            'status' => $status,
        ];
    }
}
