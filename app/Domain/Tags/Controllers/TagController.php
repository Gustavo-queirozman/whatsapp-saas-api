<?php

namespace App\Domain\Tags\Controllers;

use App\Domain\Tags\Actions\CreateTagAction;
use App\Domain\Tags\Actions\DeleteTagAction;
use App\Domain\Tags\Actions\UpdateTagAction;
use App\Domain\Tags\Models\Tag;
use App\Domain\Tags\Requests\StoreTagRequest;
use App\Domain\Tags\Requests\UpdateTagRequest;
use App\Domain\Tags\Resources\TagResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $query = Tag::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $tags = $query->get();

        return response()->json([
            'success' => true,
            'data' => TagResource::collection($tags)->resolve($request),
        ]);
    }

    public function store(StoreTagRequest $request, CreateTagAction $action): JsonResponse
    {
        $this->authorize('create', Tag::class);

        $tag = $action->execute($request->validated());

        return response()->json([
            'success' => true,
            'data' => (new TagResource($tag))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Tag $tag): JsonResponse
    {
        $this->authorize('view', $tag);

        return response()->json([
            'success' => true,
            'data' => (new TagResource($tag))->resolve($request),
        ]);
    }

    public function update(
        UpdateTagRequest $request,
        Tag $tag,
        UpdateTagAction $action
    ): JsonResponse {
        $this->authorize('update', $tag);

        $tag = $action->execute($tag, $request->validated());

        return response()->json([
            'success' => true,
            'data' => (new TagResource($tag))->resolve($request),
        ]);
    }

    public function destroy(Tag $tag, DeleteTagAction $action): JsonResponse
    {
        $this->authorize('delete', $tag);

        $action->execute($tag);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Tag removida com sucesso.',
            ],
        ]);
    }
}
