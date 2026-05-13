<?php

namespace App\Domain\Chatbot\Controllers;

use App\Domain\Chatbot\Actions\CreateBotFlowAction;
use App\Domain\Chatbot\Actions\DeleteBotFlowAction;
use App\Domain\Chatbot\Actions\UpdateBotFlowAction;
use App\Domain\Chatbot\Models\BotFlow;
use App\Domain\Chatbot\Requests\StoreBotFlowRequest;
use App\Domain\Chatbot\Requests\UpdateBotFlowRequest;
use App\Domain\Chatbot\Resources\BotFlowResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotFlowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BotFlow::class);

        $query = BotFlow::query()
            ->with(['sector', 'options.targetSector'])
            ->orderBy('name');

        if ($request->filled('sector_id')) {
            $query->where('sector_id', $request->integer('sector_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $botFlows = $query->get();

        return response()->json([
            'success' => true,
            'data' => BotFlowResource::collection($botFlows)->resolve($request),
        ]);
    }

    public function store(StoreBotFlowRequest $request, CreateBotFlowAction $action): JsonResponse
    {
        $this->authorize('create', BotFlow::class);

        $botFlow = $action->execute($request->validated());

        return response()->json([
            'success' => true,
            'data' => (new BotFlowResource($botFlow))->resolve($request),
        ], 201);
    }

    public function show(Request $request, BotFlow $botFlow): JsonResponse
    {
        $this->authorize('view', $botFlow);

        $botFlow->load(['sector', 'options.targetSector']);

        return response()->json([
            'success' => true,
            'data' => (new BotFlowResource($botFlow))->resolve($request),
        ]);
    }

    public function update(
        UpdateBotFlowRequest $request,
        BotFlow $botFlow,
        UpdateBotFlowAction $action
    ): JsonResponse {
        $this->authorize('update', $botFlow);

        $botFlow = $action->execute($botFlow, $request->validated());

        return response()->json([
            'success' => true,
            'data' => (new BotFlowResource($botFlow))->resolve($request),
        ]);
    }

    public function destroy(BotFlow $botFlow, DeleteBotFlowAction $action): JsonResponse
    {
        $this->authorize('delete', $botFlow);

        $action->execute($botFlow);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Fluxo de chatbot removido com sucesso.',
            ],
        ]);
    }
}
