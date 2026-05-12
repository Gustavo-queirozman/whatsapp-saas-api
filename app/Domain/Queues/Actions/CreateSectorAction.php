<?php

namespace App\Domain\Queues\Actions;

use App\Domain\Queues\Models\Sector;

class CreateSectorAction
{
    public function execute(array $data): Sector
    {
        return Sector::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'color' => $data['color'] ?? null,
            'settings' => $data['settings'] ?? [],
        ]);
    }
}
