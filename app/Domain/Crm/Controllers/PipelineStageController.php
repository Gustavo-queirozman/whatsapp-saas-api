<?php

namespace App\Domain\Crm\Controllers;

use App\Domain\Crm\Actions\CreatePipelineStageAction;
use App\Domain\Crm\Actions\DeletePipelineStageAction;
use App\Domain\Crm\Actions\UpdatePipelineStageAction;
use App\Domain\Crm\Models\PipelineStage;
use App\Domain\Crm\Requests\StorePipelineStageRequest;
use App\Domain\Crm\Requests\UpdatePipelineStageRequest;
use App\Domain\Crm\Resources\PipelineStageResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineStageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PipelineStage::class);

        $query = PipelineStage::query()
            ->with('pipeline')
            ->withCount('deals')
            ->orderBy('pipeline_id')
            ->orderBy('position')
            ->orderBy('id');

        if ($request->filled('pipeline_id')) {
            $query->where('pipeline_id', $request->integer('pipeline_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');

            $query->where('name', 'like', "%{$search}%");
        }

        $stages = $query->get();

        return response()->json([
            'success' => true,
            'data' => PipelineStageResource::collection($stages)->resolve($request),
        ]);
    }

    public function store(
        StorePipelineStageRequest $request,
        CreatePipelineStageAction $action
    ): JsonResponse {
        $this->authorize('create', PipelineStage::class);

        $stage = $action->execute($request->validated());

        return response()->json([
            'success' => true,
            'data' => (new PipelineStageResource($stage))->resolve($request),
        ], 201);
    }

    public function show(Request $request, PipelineStage $pipelineStage): JsonResponse
    {
        $this->authorize('view', $pipelineStage);

        $pipelineStage->load('pipeline')
            ->loadCount('deals');

        return response()->json([
            'success' => true,
            'data' => (new PipelineStageResource($pipelineStage))->resolve($request),
        ]);
    }

    public function update(
        UpdatePipelineStageRequest $request,
        PipelineStage $pipelineStage,
        UpdatePipelineStageAction $action
    ): JsonResponse {
        $this->authorize('update', $pipelineStage);

        $stage = $action->execute($pipelineStage, $request->validated());

        return response()->json([
            'success' => true,
            'data' => (new PipelineStageResource($stage))->resolve($request),
        ]);
    }

    public function destroy(
        PipelineStage $pipelineStage,
        DeletePipelineStageAction $action
    ): JsonResponse {
        $this->authorize('delete', $pipelineStage);

        $action->execute($pipelineStage);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Estagio removido com sucesso.',
            ],
        ]);
    }
}
