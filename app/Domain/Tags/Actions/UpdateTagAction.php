<?php

namespace App\Domain\Tags\Actions;

use App\Domain\Tags\Models\Tag;

class UpdateTagAction
{
    public function execute(Tag $tag, array $attributes): Tag
    {
        $tag->fill([
            'name' => $attributes['name'],
            'color' => $attributes['color'],
        ]);

        $tag->save();

        return $tag->refresh();
    }
}
