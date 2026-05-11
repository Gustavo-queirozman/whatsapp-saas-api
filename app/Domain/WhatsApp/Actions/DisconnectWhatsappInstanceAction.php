<?php

namespace App\Domain\WhatsApp\Actions;

use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Services\EvolutionGateway\EvolutionClient;
use App\Services\WhatsApp\WhatsappInstanceStatusService;

class DisconnectWhatsappInstanceAction
{
    public function __construct(
        private readonly EvolutionClient $evolutionClient,
        private readonly WhatsappInstanceStatusService $statusService,
    ) {
    }

    public function execute(WhatsappInstance $whatsappInstance): WhatsappInstance
    {
        $response = $this->evolutionClient->disconnectInstance($whatsappInstance->instance_name);

        return $this->statusService->syncFromGateway($whatsappInstance, $response, 'disconnected');
    }
}
