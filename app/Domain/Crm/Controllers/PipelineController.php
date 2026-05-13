<?php

namespace App\Domain\Crm\Controllers;

use App\Domain\Crm\Actions\CreatePipelineAction;
use App\Domain\Crm\Actions\DeletePipelineAction;
use App\Domain\Crm\Actions\UpdatePipelineAction;
use App\Domain\Crm\Models\Pipeline;
use App\Domain\Crm\Requests\StorePipelineRequest;
use App\Domain\Crm\Requests\UpdatePipelineRequest;
use App\Domain\Crm\Resources\PipelineResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Pipeline::class);

        $query = Pipeline::query()
            ->withCount(['stages', 'deals'])
            ->with(['stages' => fn ($stageQuery) => $stageQuery->withCount('deals')])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = (string) $request->string('search');

            $query->where(function ($pipelineQuery) use ($search): void {
                $pipelineQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $pipelines = $query->get();

        return response()->json([
            'success' => true,
            'data' => PipelineResource::collection($pipelines)->resolve($request),
        ]);
    }

    public function store(StorePipelineRequest $request, CreatePipelineAction $action): JsonResponse
    {
        $this->authorize('create', Pipeline::class);

        $pipeline = $action->execute($request->validated());

        return response()->json([
            'success' => true,
            'data' => (new PipelineResource($pipeline))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorize('view', $pipeline);

        $pipeline->load(['stages' => fn ($stageQuery) => $stageQuery->withCount('deals')])
            ->loadCount(['stages', 'deals']);

        return response()->json([
            'success' => true,
            'data' => (new PipelineResource($pipeline))->resolve($request),
        ]);
    }

    public function update(
        UpdatePipelineRequest $request,
        Pipeline $pipeline,
        UpdatePipelineAction $action
    ): JsonResponse {
        $this->authorize('update', $pipeline);

        $pipeline = $action->execute($pipeline, $request->validated());

        return response()->json([
            'success' => true,
            'data' => (new PipelineResource($pipeline))->resolve($request),
        ]);
    }

    public function destroy(Pipeline $pipeline, DeletePipelineAction $action): JsonResponse
    {
        $this->authorize('delete', $pipeline);

        $action->execute($pipeline);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Pipeline removido com sucesso.',
            ],
        ]);
    }
}
