<?php

namespace App\Domain\Tags\Services;

use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Tags\Models\Tag;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class TagAssignmentService
{
    public function attachToContact(Contact $contact, Tag $tag): Collection
    {
        $this->ensureSameCompany($contact->company_id, $tag->company_id);

        $contact->tags()->syncWithoutDetaching([
            $tag->id => ['company_id' => $contact->company_id],
        ]);

        return $contact->tags()->orderBy('name')->get();
    }

    public function detachFromContact(Contact $contact, Tag $tag): Collection
    {
        $this->ensureSameCompany($contact->company_id, $tag->company_id);

        $contact->tags()->detach($tag->id);

        return $contact->tags()->orderBy('name')->get();
    }

    public function attachToConversation(Conversation $conversation, Tag $tag): Collection
    {
        $this->ensureSameCompany($conversation->company_id, $tag->company_id);

        $conversation->tags()->syncWithoutDetaching([
            $tag->id => ['company_id' => $conversation->company_id],
        ]);

        return $conversation->tags()->orderBy('name')->get();
    }

    public function detachFromConversation(Conversation $conversation, Tag $tag): Collection
    {
        $this->ensureSameCompany($conversation->company_id, $tag->company_id);

        $conversation->tags()->detach($tag->id);

        return $conversation->tags()->orderBy('name')->get();
    }

    private function ensureSameCompany(int $resourceCompanyId, int $tagCompanyId): void
    {
        if ($resourceCompanyId !== $tagCompanyId) {
            throw new ModelNotFoundException();
        }
    }
}
