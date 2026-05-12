<?php

namespace App\Domain\Tags\Controllers;

use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Tags\Models\Tag;
use App\Domain\Tags\Requests\AttachTagRequest;
use App\Domain\Tags\Resources\TagResource;
use App\Domain\Tags\Services\TagAssignmentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagRelationController extends Controller
{
    public function attachToContact(
        AttachTagRequest $request,
        Contact $contact,
        TagAssignmentService $service
    ): JsonResponse {
        $this->authorize('update', $contact);
        $this->authorize('create', Tag::class);

        $tag = Tag::query()->findOrFail($request->integer('tag_id'));
        $tags = $service->attachToContact($contact, $tag);

        return response()->json([
            'success' => true,
            'data' => TagResource::collection($tags)->resolve($request),
        ]);
    }

    public function detachFromContact(
        Request $request,
        Contact $contact,
        Tag $tag,
        TagAssignmentService $service
    ): JsonResponse {
        $this->authorize('update', $contact);
        $this->authorize('delete', $tag);

        $tags = $service->detachFromContact($contact, $tag);

        return response()->json([
            'success' => true,
            'data' => TagResource::collection($tags)->resolve($request),
        ]);
    }

    public function attachToConversation(
        AttachTagRequest $request,
        Conversation $conversation,
        TagAssignmentService $service
    ): JsonResponse {
        $this->authorize('update', $conversation);
        $this->authorize('create', Tag::class);

        $tag = Tag::query()->findOrFail($request->integer('tag_id'));
        $tags = $service->attachToConversation($conversation, $tag);

        return response()->json([
            'success' => true,
            'data' => TagResource::collection($tags)->resolve($request),
        ]);
    }

    public function detachFromConversation(
        Request $request,
        Conversation $conversation,
        Tag $tag,
        TagAssignmentService $service
    ): JsonResponse {
        $this->authorize('update', $conversation);
        $this->authorize('delete', $tag);

        $tags = $service->detachFromConversation($conversation, $tag);

        return response()->json([
            'success' => true,
            'data' => TagResource::collection($tags)->resolve($request),
        ]);
    }
}
