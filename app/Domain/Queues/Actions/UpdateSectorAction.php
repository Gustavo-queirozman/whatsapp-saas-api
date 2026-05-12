<?php

namespace App\Domain\Queues\Actions;

use App\Domain\Queues\Models\Sector;

class UpdateSectorAction
{
    public function execute(Sector $sector, array $data): Sector
    {
        $sector->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'color' => $data['color'] ?? null,
            'settings' => $data['settings'] ?? [],
        ])->save();

        return $sector->refresh();
    }
}
