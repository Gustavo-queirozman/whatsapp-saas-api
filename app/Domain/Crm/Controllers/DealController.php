<?php

namespace App\Domain\Crm\Controllers;

use App\Domain\Crm\Actions\CreateDealAction;
use App\Domain\Crm\Actions\DeleteDealAction;
use App\Domain\Crm\Actions\MoveDealStageAction;
use App\Domain\Crm\Actions\UpdateDealAction;
use App\Domain\Crm\Models\Deal;
use App\Domain\Crm\Models\PipelineStage;
use App\Domain\Crm\Requests\MoveDealStageRequest;
use App\Domain\Crm\Requests\StoreDealRequest;
use App\Domain\Crm\Requests\UpdateDealRequest;
use App\Domain\Crm\Resources\DealResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Deal::class);

        $query = Deal::query()
            ->with(['pipeline', 'stage', 'contact', 'assignedUser'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($request->filled('pipeline_id')) {
            $query->where('pipeline_id', $request->integer('pipeline_id'));
        }

        if ($request->filled('pipeline_stage_id')) {
            $query->where('pipeline_stage_id', $request->integer('pipeline_stage_id'));
        }

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->integer('contact_id'));
        }

        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->integer('assigned_user_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');

            $query->where(function ($dealQuery) use ($search): void {
                $dealQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('contact', fn ($contactQuery) => $contactQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $deals = $query->get();

        return response()->json([
            'success' => true,
            'data' => DealResource::collection($deals)->resolve($request),
        ]);
    }

    public function store(StoreDealRequest $request, CreateDealAction $action): JsonResponse
    {
        $this->authorize('create', Deal::class);

        $deal = $action->execute($request->validated());

        return response()->json([
            'success' => true,
            'data' => (new DealResource($deal))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Deal $deal): JsonResponse
    {
        $this->authorize('view', $deal);

        $deal->loadMissing(['pipeline', 'stage', 'contact', 'assignedUser']);

        return response()->json([
            'success' => true,
            'data' => (new DealResource($deal))->resolve($request),
        ]);
    }

    public function update(
        UpdateDealRequest $request,
        Deal $deal,
        UpdateDealAction $action
    ): JsonResponse {
        $this->authorize('update', $deal);

        $deal = $action->execute($deal, $request->validated());

        return response()->json([
            'success' => true,
            'data' => (new DealResource($deal))->resolve($request),
        ]);
    }

    public function destroy(Deal $deal, DeleteDealAction $action): JsonResponse
    {
        $this->authorize('delete', $deal);

        $action->execute($deal);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Deal removido com sucesso.',
            ],
        ]);
    }

    public function moveStage(
        MoveDealStageRequest $request,
        Deal $deal,
        MoveDealStageAction $action
    ): JsonResponse {
        $this->authorize('moveStage', $deal);

        $stage = PipelineStage::query()->findOrFail($request->integer('pipeline_stage_id'));
        $deal = $action->execute($deal, $stage);

        return response()->json([
            'success' => true,
            'data' => (new DealResource($deal))->resolve($request),
        ]);
    }
}
