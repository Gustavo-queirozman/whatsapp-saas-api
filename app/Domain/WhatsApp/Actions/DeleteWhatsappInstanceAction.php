<?php

namespace App\Domain\WhatsApp\Actions;

use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Services\EvolutionGateway\EvolutionClient;

class DeleteWhatsappInstanceAction
{
    public function __construct(
        private readonly EvolutionClient $evolutionClient,
    ) {
    }

    public function execute(WhatsappInstance $whatsappInstance): void
    {
        $this->evolutionClient->deleteInstance($whatsappInstance->instance_name);
        $whatsappInstance->delete();
    }
}
