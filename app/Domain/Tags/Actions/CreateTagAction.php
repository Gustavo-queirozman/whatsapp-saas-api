<?php

namespace App\Domain\Tags\Actions;

use App\Domain\Tags\Models\Tag;

class CreateTagAction
{
    public function execute(array $attributes): Tag
    {
        return Tag::query()->create([
            'name' => $attributes['name'],
            'color' => $attributes['color'],
        ]);
    }
}
