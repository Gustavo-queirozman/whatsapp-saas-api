<?php

namespace App\Domain\Queues\Controllers;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Resources\ConversationResource;
use App\Domain\Queues\Actions\AttachSectorUserAction;
use App\Domain\Queues\Actions\CreateSectorAction;
use App\Domain\Queues\Actions\DeleteSectorAction;
use App\Domain\Queues\Actions\DetachSectorUserAction;
use App\Domain\Queues\Actions\UpdateSectorAction;
use App\Domain\Queues\Models\Sector;
use App\Domain\Queues\Requests\AttachSectorUserRequest;
use App\Domain\Queues\Requests\StoreSectorRequest;
use App\Domain\Queues\Requests\UpdateSectorRequest;
use App\Domain\Queues\Resources\SectorResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sector::class);

        $query = Sector::query()
            ->withCount('users')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = (string) $request->string('search');

            $query->where(function ($sectorQuery) use ($search): void {
                $sectorQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $sectors = $query->get();

        return response()->json([
            'success' => true,
            'data' => SectorResource::collection($sectors)->resolve($request),
        ]);
    }

    public function store(StoreSectorRequest $request, CreateSectorAction $action): JsonResponse
    {
        $this->authorize('create', Sector::class);

        $sector = $action->execute($request->validated())
            ->loadCount('users');

        return response()->json([
            'success' => true,
            'data' => (new SectorResource($sector))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Sector $sector): JsonResponse
    {
        $this->authorize('view', $sector);

        $sector->load('users')
            ->loadCount('users');

        return response()->json([
            'success' => true,
            'data' => (new SectorResource($sector))->resolve($request),
        ]);
    }

    public function update(
        UpdateSectorRequest $request,
        Sector $sector,
        UpdateSectorAction $action
    ): JsonResponse {
        $this->authorize('update', $sector);

        $sector = $action->execute($sector, $request->validated())
            ->load('users')
            ->loadCount('users');

        return response()->json([
            'success' => true,
            'data' => (new SectorResource($sector))->resolve($request),
        ]);
    }

    public function destroy(Sector $sector, DeleteSectorAction $action): JsonResponse
    {
        $this->authorize('delete', $sector);

        $action->execute($sector);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Setor removido com sucesso.',
            ],
        ]);
    }

    public function attachUser(
        AttachSectorUserRequest $request,
        Sector $sector,
        AttachSectorUserAction $action
    ): JsonResponse {
        $this->authorize('attachUser', $sector);

        $sector = $action->execute($sector, $request->integer('user_id'))
            ->load('users')
            ->loadCount('users');

        return response()->json([
            'success' => true,
            'data' => (new SectorResource($sector))->resolve($request),
        ]);
    }

    public function detachUser(
        Request $request,
        Sector $sector,
        int $userId,
        DetachSectorUserAction $action
    ): JsonResponse {
        $this->authorize('detachUser', $sector);

        $sector = $action->execute($sector, $userId)
            ->load('users')
            ->loadCount('users');

        return response()->json([
            'success' => true,
            'data' => (new SectorResource($sector))->resolve($request),
        ]);
    }

    public function queue(Request $request, Sector $sector): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);
        $this->authorize('view', $sector);

        $conversations = $sector->conversations()
            ->with(['contact', 'sector', 'whatsappInstance', 'assignedUser', 'tags'])
            ->withCount('messages')
            ->where('status', Conversation::STATUS_WAITING)
            ->orderByDesc('last_message_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'sector' => (new SectorResource($sector->loadCount('users')))->resolve($request),
                'summary' => [
                    'waiting' => $sector->conversations()->where('status', Conversation::STATUS_WAITING)->count(),
                    'open' => $sector->conversations()->where('status', Conversation::STATUS_OPEN)->count(),
                    'closed' => $sector->conversations()->where('status', Conversation::STATUS_CLOSED)->count(),
                ],
                'conversations' => ConversationResource::collection($conversations)->resolve($request),
            ],
        ]);
    }
}
