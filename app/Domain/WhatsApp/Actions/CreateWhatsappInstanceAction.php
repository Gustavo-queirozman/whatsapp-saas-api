<?php

namespace App\Domain\WhatsApp\Actions;

use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\DTOs\WhatsApp\CreateWhatsappInstanceData;
use App\Services\EvolutionGateway\EvolutionClient;
use App\Services\WhatsApp\WhatsappInstanceStatusService;

class CreateWhatsappInstanceAction
{
    public function __construct(
        private readonly EvolutionClient $evolutionClient,
        private readonly WhatsappInstanceStatusService $statusService,
    ) {
    }

    public function execute(CreateWhatsappInstanceData $data): WhatsappInstance
    {
        $response = $this->evolutionClient->createInstance(
            $data->instanceName,
            true,
            $data->phoneNumber,
        );

        $whatsappInstance = WhatsappInstance::query()->create([
            'company_id' => $data->companyId,
            'sector_id' => $data->sectorId,
            'instance_name' => $data->instanceName,
            'phone_number' => $data->phoneNumber,
            'status' => $this->statusService->normalize(
                (string) data_get($response, 'instance.status', 'connecting'),
                'connecting',
            ),
            'metadata' => $data->metadata,
        ]);

        return $this->statusService->syncFromGateway($whatsappInstance, $response, 'connecting');
    }
}
