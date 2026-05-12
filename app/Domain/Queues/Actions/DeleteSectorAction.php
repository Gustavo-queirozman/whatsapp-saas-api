<?php

namespace App\Domain\Queues\Actions;

use App\Domain\Queues\Models\Sector;
use Illuminate\Validation\ValidationException;

class DeleteSectorAction
{
    public function execute(Sector $sector): void
    {
        if ($sector->conversations()->exists()) {
            throw ValidationException::withMessages([
                'sector' => 'Nao e possivel remover um setor com conversas vinculadas.',
            ]);
        }

        if ($sector->whatsappInstances()->exists()) {
            throw ValidationException::withMessages([
                'sector' => 'Nao e possivel remover um setor com instancias WhatsApp vinculadas.',
            ]);
        }

        $sector->delete();
    }
}
