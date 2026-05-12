<?php

namespace App\Domain\Tags\Actions;

use App\Domain\Tags\Models\Tag;

class DeleteTagAction
{
    public function execute(Tag $tag): void
    {
        $tag->delete();
    }
}
